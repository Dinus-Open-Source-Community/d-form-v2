import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\FeaturePageController::__invoke
* @see Http/Controllers/FeaturePageController.php:12
* @route '/features'
*/
const FeaturePageController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: FeaturePageController.url(options),
    method: 'get',
})

FeaturePageController.definition = {
    methods: ["get","head"],
    url: '/features',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\FeaturePageController::__invoke
* @see Http/Controllers/FeaturePageController.php:12
* @route '/features'
*/
FeaturePageController.url = (options?: RouteQueryOptions) => {
    return FeaturePageController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\FeaturePageController::__invoke
* @see Http/Controllers/FeaturePageController.php:12
* @route '/features'
*/
FeaturePageController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: FeaturePageController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\FeaturePageController::__invoke
* @see Http/Controllers/FeaturePageController.php:12
* @route '/features'
*/
FeaturePageController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: FeaturePageController.url(options),
    method: 'head',
})

export default FeaturePageController