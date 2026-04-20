import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController::__invoke
* @see Http/Controllers/Dashboard/Events/Forms/FieldOperationController.php:18
* @route '/dashboard/forms/{form}/fields'
*/
const FieldOperationController = (args: { form: string | number } | [form: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: FieldOperationController.url(args, options),
    method: 'post',
})

FieldOperationController.definition = {
    methods: ["post"],
    url: '/dashboard/forms/{form}/fields',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController::__invoke
* @see Http/Controllers/Dashboard/Events/Forms/FieldOperationController.php:18
* @route '/dashboard/forms/{form}/fields'
*/
FieldOperationController.url = (args: { form: string | number } | [form: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return FieldOperationController.definition.url
            .replace('{form}', parsedArgs.form.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController::__invoke
* @see Http/Controllers/Dashboard/Events/Forms/FieldOperationController.php:18
* @route '/dashboard/forms/{form}/fields'
*/
FieldOperationController.post = (args: { form: string | number } | [form: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: FieldOperationController.url(args, options),
    method: 'post',
})

export default FieldOperationController