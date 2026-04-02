import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
const PageController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PageController.url(options),
    method: 'get',
})

PageController.definition = {
    methods: ["get","head"],
    url: '/auth',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
PageController.url = (options?: RouteQueryOptions) => {
    return PageController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
PageController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PageController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
PageController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PageController.url(options),
    method: 'head',
})

export default PageController