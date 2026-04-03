import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\EventsController::index
* @see Http/Controllers/EventsController.php:12
* @route '/events'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/events',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EventsController::index
* @see Http/Controllers/EventsController.php:12
* @route '/events'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EventsController::index
* @see Http/Controllers/EventsController.php:12
* @route '/events'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\EventsController::index
* @see Http/Controllers/EventsController.php:12
* @route '/events'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\EventsController::show
* @see Http/Controllers/EventsController.php:17
* @route '/events/{event}'
*/
export const show = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/events/{event}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EventsController::show
* @see Http/Controllers/EventsController.php:17
* @route '/events/{event}'
*/
show.url = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: args.event,
    }

    return show.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EventsController::show
* @see Http/Controllers/EventsController.php:17
* @route '/events/{event}'
*/
show.get = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\EventsController::show
* @see Http/Controllers/EventsController.php:17
* @route '/events/{event}'
*/
show.head = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const EventsController = { index, show }

export default EventsController