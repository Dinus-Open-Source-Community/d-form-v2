# External Course and Redeem Code API

This guide documents the endpoints an external application can use to retrieve active courses and create course redeem codes.

## Base URL

Use the DU backend API origin, not the public DU frontend origin.

Production:

```text
https://du.zappz.my.id
```

The JSON API is mounted under `/api`. The public frontend paths without `/api` return the DU SPA. Local development may use an equivalent DU backend origin.

## 1. List active courses

```http
GET /api/courses?page=1&per_page=100
```

This endpoint is public and does not require an API key. It returns courses whose status is `ACTIVE`. Optional filters can narrow results further.

### Query parameters

| Name | Required | Default | Description |
| --- | --- | --- | --- |
| `page` | No | `1` | Page number, starting at 1. |
| `per_page` | No | `10` | Results per page; maximum `100`. |
| `title` | No | — | Case-insensitive title search. |
| `mentor_id` | No | — | Filter by mentor UID or UID prefix. |
| `status` | No | `ACTIVE` only | Additional status filter; cannot include non-active courses. |
| `level` | No | — | Filter by course level. |
| `course_category_id` / `category_uid` | No | — | Filter by category UID or UID prefix. |
| `course_type_id` / `class_type_id` | No | — | Filter by course type UID or UID prefix. |

### Example

```bash
curl --request GET 'https://du.zappz.my.id/api/courses?page=1&per_page=100&status=ACTIVE'
```

### Successful response

The response envelope contains `data.courses` and pagination metadata in `data.meta`. Each course includes public details such as `uid`, `title`, `slug`, `status`, `is_published`, `description`, `level`, and `mentors`. Production verification on 22 November 2026 returned HTTP 200, `success: true`, one page, and five active courses.

```json
{
  "success": true,
  "message": "Courses retrieved successfully",
  "data": {
    "courses": [
      {
        "uid": "f6823c2e",
        "title": "DevOps Essentials"}},{,
        "slug": "dasar-pemrograman-golang",
        "status": "ACTIVE",
        "is_published": true
      }
    ],
    "meta": {
      "total": 1,
      "per_page": 100,
      "current_page": 1,
      "total_pages": 1
    }
  },
  "error": null
}
```

The course-list response provides the course `uid` returned by the deployed API. Current production responses use 8-character UIDs, such as `f6823c2e`. Pass that exact value as `course_uid` when creating a redeem code. The backend also accepts UID prefixes, but rejects ambiguous prefixes.

## 2. Create a redeem code

```http
POST /api/external/redeem-codes
```

This endpoint requires the server-side API key configured in `REDEEM_CODE_API_KEY`. Send it in the `X-API-Key` header. Never embed this key in a browser or other publicly distributed client.

### Headers

```http
Content-Type: application/json
X-API-Key: <REDEEM_CODE_API_KEY>
```

If the environment variable is empty, the endpoint rejects requests.

### Request body

| Field | Required | Default | Description |
| --- | --- | --- | --- |
| `course_uid` | Yes | — | Course UID or UID prefix from the active course list. |
| `target_email` | No | — | Restrict code to this email address. The account does not need to exist yet. |
| `target_user_uid` | No | — | Restrict code to an existing student account. If both target fields are set, this user takes precedence. |
| `code` | No | Generated | Custom code, 4–64 characters; letters, numbers, `-`, and `_` only. |
| `prefix` | No | `DU` | Prefix for the generated code. It is uppercased; ignored when `code` is provided. |
| `max_uses` | No | `1` | Maximum successful redemptions. Values less than 1 use the default `1`. |
| `expires_at` | No | No expiration | Expiration timestamp in RFC 3339 format. Must be in the future. |
| `is_active` | No | `true` | Whether the code is active immediately after creation. |

The course must exist; it does not need to be active or published. When a target email already belongs to a user, that user must not already be enrolled in the course. A target user must have the `student` role and must not already be enrolled.

Example request body:

```json
{
  "course_uid": "f6823c2e",
  "target_email": "buyer@example.com",
  "max_uses": 1,
  "expires_at": "2027-01-31T23:59:59Z"
}
```

### Example

```bash
curl --request POST 'https://du.zappz.my.id/api/external/redeem-codes' \
  --header 'Content-Type: application/json' \
  --header 'X-API-Key: <REDEEM_CODE_API_KEY>' \
  --data '{
    "course_uid": "f6823c2e",
    "target_email": "buyer@example.com",
    "max_uses": 1,
    "expires_at": "2027-01-31T23:59:59Z"
  }'
```

### Successful response

The endpoint returns HTTP `201 Created`. Keep the returned `code` and deliver it to the intended customer.

```json
{
  "success": true,
  "message": "Kode redeem berhasil dibuat.",
  "data": {
    "uid": "7e484e3e-436e-44f3-b789-fdf2d0e653da",
    "code": "DU-X7K2-9B4Z",
    "course_uid": "f6823c2e",
    "course_title": "Dasar Pemrograman Golang",
    "target_email": "buyer@example.com",
    "max_uses": 1,
    "used_count": 0,
    "is_active": true,
    "expires_at": "2027-01-31T23:59:59Z",
    "created_by_uid": "",
    "created_at": "2026-11-08T10:00:00Z"
  }
}
```

### Error responses

| HTTP status | Meaning |
| --- | --- |
| `400 Bad Request` | Invalid body, course UID, code, email, or expiration; target user is not a student; or target is already enrolled. |
| `401 Unauthorized` | API key is missing, invalid, or not configured on the server. |
| `404 Not Found` | Target user does not exist. Invalid or unknown course UIDs return `400`. |
| `409 Conflict` | Custom code already exists. |
| `500 Internal Server Error` | Server could not generate or save the code. |

Errors use the standard response envelope with `success: false`, a `message`, and an `error` value.

## Redeeming the code

Customers redeem the returned code through the student-authenticated redeem endpoint. The external application should create and deliver codes; it should not attempt to enroll users directly.
