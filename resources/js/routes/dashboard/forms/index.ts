import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController::__invoke
* @see Http/Controllers/Dashboard/Events/Forms/FieldOperationController.php:18
* @route '/dashboard/forms/{form}/fields'
*/
export const fields = (args: { form: string | number } | [form: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fields.url(args, options),
    method: 'post',
})

fields.definition = {
    methods: ["post"],
    url: '/dashboard/forms/{form}/fields',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController::__invoke
* @see Http/Controllers/Dashboard/Events/Forms/FieldOperationController.php:18
* @route '/dashboard/forms/{form}/fields'
*/
fields.url = (args: { form: string | number } | [form: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { form: args }
    }

    if (Array.isArray(args)) {
        args = {
            form: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        form: args.form,
    }

    return fields.definition.url
            .replace('{form}', parsedArgs.form.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController::__invoke
* @see Http/Controllers/Dashboard/Events/Forms/FieldOperationController.php:18
* @route '/dashboard/forms/{form}/fields'
*/
fields.post = (args: { form: string | number } | [form: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fields.url(args, options),
    method: 'post',
})

const forms = {
    fields: Object.assign(fields, fields),
}

export default forms