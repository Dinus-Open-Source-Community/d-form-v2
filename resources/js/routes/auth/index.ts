import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import google723582 from './google'
import githubF226c8 from './github'
/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/auth',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\PageController::__invoke
* @see app/Http/Controllers/Auth/PageController.php:15
* @route '/auth'
*/
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::google
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
export const google = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: google.url(options),
    method: 'get',
})

google.definition = {
    methods: ["get","head"],
    url: '/auth/google',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\OAuthController::google
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
google.url = (options?: RouteQueryOptions) => {
    return google.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\OAuthController::google
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
google.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: google.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::google
* @see app/Http/Controllers/Auth/OAuthController.php:18
* @route '/auth/google'
*/
google.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: google.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::github
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
export const github = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: github.url(options),
    method: 'get',
})

github.definition = {
    methods: ["get","head"],
    url: '/auth/github',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\OAuthController::github
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
github.url = (options?: RouteQueryOptions) => {
    return github.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\OAuthController::github
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
github.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: github.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\OAuthController::github
* @see app/Http/Controllers/Auth/OAuthController.php:96
* @route '/auth/github'
*/
github.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: github.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\LogoutController::__invoke
* @see app/Http/Controllers/Auth/LogoutController.php:11
* @route '/auth/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/auth/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\LogoutController::__invoke
* @see app/Http/Controllers/Auth/LogoutController.php:11
* @route '/auth/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\LogoutController::__invoke
* @see app/Http/Controllers/Auth/LogoutController.php:11
* @route '/auth/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

const auth = {
    login: Object.assign(login, login),
    google: Object.assign(google, google723582),
    github: Object.assign(github, githubF226c8),
    logout: Object.assign(logout, logout),
}

export default auth