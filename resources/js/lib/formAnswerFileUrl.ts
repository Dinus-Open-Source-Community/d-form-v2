/** Returns the authenticated relative URL for a private form-answer file route. */
export function extractFormAnswerFileUrl(value: string): string | null {
    let parsedUrl: URL;

    try {
        parsedUrl = new URL(value, 'http://localhost');
    } catch {
        return null;
    }

    const segments = parsedUrl.pathname.split('/').filter(Boolean);
    const routeIndex = segments.indexOf('form-answers');
    const requiredRouteLength = 5;

    if (
        routeIndex < 0 ||
        segments[routeIndex + 1] === undefined ||
        segments[routeIndex + 2] !== 'files' ||
        segments[routeIndex + 3] === undefined ||
        segments[routeIndex + 4] === undefined
    ) {
        return null;
    }

    const route = `/${segments.slice(routeIndex, routeIndex + requiredRouteLength).join('/')}`;
    return `${route}${parsedUrl.search}${parsedUrl.hash}`;
}

/** Extract every individually authorized private URL from a serialized answer. */
export function extractFormAnswerFileUrls(value: unknown): string[] {
    const values = Array.isArray(value) ? value : [value];

    return values.flatMap((item: unknown): string[] => {
        if (typeof item !== 'string') {
            return [];
        }

        const url = extractFormAnswerFileUrl(item.trim());
        return url === null ? [] : [url];
    });
}
