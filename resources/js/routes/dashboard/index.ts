import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import events from './events'
/**
* @see routes/web/admin/event.php:8
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
* @see routes/web/admin/event.php:8
* @route '/dashboard'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see routes/web/admin/event.php:8
* @route '/dashboard'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see routes/web/admin/event.php:8
* @route '/dashboard'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

const dashboard = {
    home: Object.assign(home, home),
    events: Object.assign(events, events),
}

export default dashboard