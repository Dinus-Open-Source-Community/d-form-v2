import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::index
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:14
* @route '/dashboard/events/{event}/forms'
*/
export const index = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}/forms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::index
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:14
* @route '/dashboard/events/{event}/forms'
*/
index.url = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::index
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:14
* @route '/dashboard/events/{event}/forms'
*/
index.get = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::index
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:14
* @route '/dashboard/events/{event}/forms'
*/
index.head = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::create
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:22
* @route '/dashboard/events/{event}/forms/create'
*/
export const create = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}/forms/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::create
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:22
* @route '/dashboard/events/{event}/forms/create'
*/
create.url = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return create.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::create
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:22
* @route '/dashboard/events/{event}/forms/create'
*/
create.get = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::create
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:22
* @route '/dashboard/events/{event}/forms/create'
*/
create.head = (args: { event: string | number } | [event: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::show
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:32
* @route '/dashboard/events/{event}/forms/{form}'
*/
export const show = (args: { event: string | number, form: string | number } | [event: string | number, form: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}/forms/{form}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::show
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:32
* @route '/dashboard/events/{event}/forms/{form}'
*/
show.url = (args: { event: string | number, form: string | number } | [event: string | number, form: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            event: args[0],
            form: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: args.event,
        form: args.form,
    }

    return show.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace('{form}', parsedArgs.form.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::show
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:32
* @route '/dashboard/events/{event}/forms/{form}'
*/
show.get = (args: { event: string | number, form: string | number } | [event: string | number, form: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::show
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:32
* @route '/dashboard/events/{event}/forms/{form}'
*/
show.head = (args: { event: string | number, form: string | number } | [event: string | number, form: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::edit
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:46
* @route '/dashboard/events/{event}/forms/{form}/edit'
*/
export const edit = (args: { event: string | number, form: string | { id: string } } | [event: string | number, form: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/dashboard/events/{event}/forms/{form}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::edit
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:46
* @route '/dashboard/events/{event}/forms/{form}/edit'
*/
edit.url = (args: { event: string | number, form: string | { id: string } } | [event: string | number, form: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            event: args[0],
            form: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        event: args.event,
        form: typeof args.form === 'object'
        ? args.form.id
        : args.form,
    }

    return edit.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace('{form}', parsedArgs.form.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::edit
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:46
* @route '/dashboard/events/{event}/forms/{form}/edit'
*/
edit.get = (args: { event: string | number, form: string | { id: string } } | [event: string | number, form: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FormController::edit
* @see Http/Controllers/Dashboard/Events/Forms/FormController.php:46
* @route '/dashboard/events/{event}/forms/{form}/edit'
*/
edit.head = (args: { event: string | number, form: string | { id: string } } | [event: string | number, form: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const FormController = { index, create, show, edit }

export default FormController