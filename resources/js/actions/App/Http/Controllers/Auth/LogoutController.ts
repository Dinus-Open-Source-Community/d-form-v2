import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\LogoutController::__invoke
* @see Http/Controllers/Auth/LogoutController.php:11
* @route '/auth/logout'
*/
const LogoutController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LogoutController.url(options),
    method: 'post',
})

LogoutController.definition = {
    methods: ["post"],
    url: '/auth/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\LogoutController::__invoke
* @see Http/Controllers/Auth/LogoutController.php:11
* @route '/auth/logout'
*/
LogoutController.url = (options?: RouteQueryOptions) => {
    return LogoutController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\LogoutController::__invoke
* @see Http/Controllers/Auth/LogoutController.php:11
* @route '/auth/logout'
*/
LogoutController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LogoutController.url(options),
    method: 'post',
})

export default LogoutController