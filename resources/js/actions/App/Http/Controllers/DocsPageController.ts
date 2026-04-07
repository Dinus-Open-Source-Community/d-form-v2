import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DocsPageController::__invoke
* @see Http/Controllers/DocsPageController.php:12
* @route '/docs'
*/
const DocsPageController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DocsPageController.url(options),
    method: 'get',
})

DocsPageController.definition = {
    methods: ["get","head"],
    url: '/docs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DocsPageController::__invoke
* @see Http/Controllers/DocsPageController.php:12
* @route '/docs'
*/
DocsPageController.url = (options?: RouteQueryOptions) => {
    return DocsPageController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocsPageController::__invoke
* @see Http/Controllers/DocsPageController.php:12
* @route '/docs'
*/
DocsPageController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DocsPageController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DocsPageController::__invoke
* @see Http/Controllers/DocsPageController.php:12
* @route '/docs'
*/
DocsPageController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DocsPageController.url(options),
    method: 'head',
})

export default DocsPageController