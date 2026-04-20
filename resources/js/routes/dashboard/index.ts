import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import events from './events'
import forms from './forms'
/**
* @see \App\Http\Controllers\Dashboard\HomeController::__invoke
* @see Http/Controllers/Dashboard/HomeController.php:9
* @route '/dashboard'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\HomeController::__invoke
* @see Http/Controllers/Dashboard/HomeController.php:9
* @route '/dashboard'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\HomeController::__invoke
* @see Http/Controllers/Dashboard/HomeController.php:9
* @route '/dashboard'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\HomeController::__invoke
* @see Http/Controllers/Dashboard/HomeController.php:9
* @route '/dashboard'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

const dashboard = {
    events: Object.assign(events, events),
    forms: Object.assign(forms, forms),
    home: Object.assign(home, home),
}

export default dashboard