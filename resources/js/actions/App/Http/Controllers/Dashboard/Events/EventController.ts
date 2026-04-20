import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::registrationStatus
* @see Http/Controllers/Dashboard/Events/EventController.php:86
* @route '/dashboard/events/{event}/registration-status'
*/
export const registrationStatus = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: registrationStatus.url(args, options),
    method: 'get',
})

registrationStatus.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}/registration-status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::registrationStatus
* @see Http/Controllers/Dashboard/Events/EventController.php:86
* @route '/dashboard/events/{event}/registration-status'
*/
registrationStatus.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { event: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: typeof args.event === 'object'
        ? args.event.id
        : args.event,
    }

    return registrationStatus.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::registrationStatus
* @see Http/Controllers/Dashboard/Events/EventController.php:86
* @route '/dashboard/events/{event}/registration-status'
*/
registrationStatus.get = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: registrationStatus.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::registrationStatus
* @see Http/Controllers/Dashboard/Events/EventController.php:86
* @route '/dashboard/events/{event}/registration-status'
*/
registrationStatus.head = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: registrationStatus.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::restore
* @see Http/Controllers/Dashboard/Events/EventController.php:134
* @route '/dashboard/events/{event}/restore'
*/
export const restore = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/dashboard/events/{event}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::restore
* @see Http/Controllers/Dashboard/Events/EventController.php:134
* @route '/dashboard/events/{event}/restore'
*/
restore.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { event: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: typeof args.event === 'object'
        ? args.event.id
        : args.event,
    }

    return restore.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::restore
* @see Http/Controllers/Dashboard/Events/EventController.php:134
* @route '/dashboard/events/{event}/restore'
*/
restore.post = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::index
* @see Http/Controllers/Dashboard/Events/EventController.php:27
* @route '/dashboard/events'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dashboard/events',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::index
* @see Http/Controllers/Dashboard/Events/EventController.php:27
* @route '/dashboard/events'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::index
* @see Http/Controllers/Dashboard/Events/EventController.php:27
* @route '/dashboard/events'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::index
* @see Http/Controllers/Dashboard/Events/EventController.php:27
* @route '/dashboard/events'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::create
* @see Http/Controllers/Dashboard/Events/EventController.php:47
* @route '/dashboard/events/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::create
* @see Http/Controllers/Dashboard/Events/EventController.php:47
* @route '/dashboard/events/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::create
* @see Http/Controllers/Dashboard/Events/EventController.php:47
* @route '/dashboard/events/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::create
* @see Http/Controllers/Dashboard/Events/EventController.php:47
* @route '/dashboard/events/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::store
* @see Http/Controllers/Dashboard/Events/EventController.php:56
* @route '/dashboard/events'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/dashboard/events',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::store
* @see Http/Controllers/Dashboard/Events/EventController.php:56
* @route '/dashboard/events'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::store
* @see Http/Controllers/Dashboard/Events/EventController.php:56
* @route '/dashboard/events'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::show
* @see Http/Controllers/Dashboard/Events/EventController.php:71
* @route '/dashboard/events/{event}'
*/
export const show = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::show
* @see Http/Controllers/Dashboard/Events/EventController.php:71
* @route '/dashboard/events/{event}'
*/
show.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { event: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: typeof args.event === 'object'
        ? args.event.id
        : args.event,
    }

    return show.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::show
* @see Http/Controllers/Dashboard/Events/EventController.php:71
* @route '/dashboard/events/{event}'
*/
show.get = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::show
* @see Http/Controllers/Dashboard/Events/EventController.php:71
* @route '/dashboard/events/{event}'
*/
show.head = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::edit
* @see Http/Controllers/Dashboard/Events/EventController.php:95
* @route '/dashboard/events/{event}/edit'
*/
export const edit = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::edit
* @see Http/Controllers/Dashboard/Events/EventController.php:95
* @route '/dashboard/events/{event}/edit'
*/
edit.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { event: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: typeof args.event === 'object'
        ? args.event.id
        : args.event,
    }

    return edit.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::edit
* @see Http/Controllers/Dashboard/Events/EventController.php:95
* @route '/dashboard/events/{event}/edit'
*/
edit.get = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::edit
* @see Http/Controllers/Dashboard/Events/EventController.php:95
* @route '/dashboard/events/{event}/edit'
*/
edit.head = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::update
* @see Http/Controllers/Dashboard/Events/EventController.php:105
* @route '/dashboard/events/{event}'
*/
export const update = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/dashboard/events/{event}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::update
* @see Http/Controllers/Dashboard/Events/EventController.php:105
* @route '/dashboard/events/{event}'
*/
update.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { event: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: typeof args.event === 'object'
        ? args.event.id
        : args.event,
    }

    return update.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::update
* @see Http/Controllers/Dashboard/Events/EventController.php:105
* @route '/dashboard/events/{event}'
*/
update.put = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::update
* @see Http/Controllers/Dashboard/Events/EventController.php:105
* @route '/dashboard/events/{event}'
*/
update.patch = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::destroy
* @see Http/Controllers/Dashboard/Events/EventController.php:120
* @route '/dashboard/events/{event}'
*/
export const destroy = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/dashboard/events/{event}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::destroy
* @see Http/Controllers/Dashboard/Events/EventController.php:120
* @route '/dashboard/events/{event}'
*/
destroy.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { event: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            event: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: typeof args.event === 'object'
        ? args.event.id
        : args.event,
    }

    return destroy.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\EventController::destroy
* @see Http/Controllers/Dashboard/Events/EventController.php:120
* @route '/dashboard/events/{event}'
*/
destroy.delete = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const EventController = { registrationStatus, restore, index, create, store, show, edit, update, destroy }

export default EventController