import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGoogle
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
export const redirectToGoogle = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirectToGoogle.url(options),
    method: 'get',
})

redirectToGoogle.definition = {
    methods: ["get","head"],
    url: '/auth/google',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGoogle
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
redirectToGoogle.url = (options?: RouteQueryOptions) => {
    return redirectToGoogle.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGoogle
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
redirectToGoogle.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirectToGoogle.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGoogle
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
redirectToGoogle.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: redirectToGoogle.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGoogleCallback
* @see app/Http/Controllers/Auth/OAuthController.php:26
* @route '/auth/google/callback'
*/
export const handleGoogleCallback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: handleGoogleCallback.url(options),
    method: 'get',
})

handleGoogleCallback.definition = {
    methods: ["get","head"],
    url: '/auth/google/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGoogleCallback
* @see app/Http/Controllers/Auth/OAuthController.php:26
* @route '/auth/google/callback'
*/
handleGoogleCallback.url = (options?: RouteQueryOptions) => {
    return handleGoogleCallback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGoogleCallback
* @see app/Http/Controllers/Auth/OAuthController.php:26
* @route '/auth/google/callback'
*/
handleGoogleCallback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: handleGoogleCallback.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGoogleCallback
* @see app/Http/Controllers/Auth/OAuthController.php:26
* @route '/auth/google/callback'
*/
handleGoogleCallback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: handleGoogleCallback.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGithub
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
export const redirectToGithub = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirectToGithub.url(options),
    method: 'get',
})

redirectToGithub.definition = {
    methods: ["get","head"],
    url: '/auth/github',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGithub
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
redirectToGithub.url = (options?: RouteQueryOptions) => {
    return redirectToGithub.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGithub
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
redirectToGithub.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirectToGithub.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::redirectToGithub
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
redirectToGithub.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: redirectToGithub.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGithubCallback
* @see app/Http/Controllers/Auth/OAuthController.php:104
* @route '/auth/github/callback'
*/
export const handleGithubCallback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: handleGithubCallback.url(options),
    method: 'get',
})

handleGithubCallback.definition = {
    methods: ["get","head"],
    url: '/auth/github/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGithubCallback
* @see app/Http/Controllers/Auth/OAuthController.php:104
* @route '/auth/github/callback'
*/
handleGithubCallback.url = (options?: RouteQueryOptions) => {
    return handleGithubCallback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGithubCallback
* @see app/Http/Controllers/Auth/OAuthController.php:104
* @route '/auth/github/callback'
*/
handleGithubCallback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: handleGithubCallback.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::handleGithubCallback
* @see app/Http/Controllers/Auth/OAuthController.php:104
* @route '/auth/github/callback'
*/
handleGithubCallback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: handleGithubCallback.url(options),
    method: 'head',
})

const OAuthController = { redirectToGoogle, handleGoogleCallback, redirectToGithub, handleGithubCallback }

export default OAuthController