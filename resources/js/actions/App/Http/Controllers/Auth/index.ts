import PageController from './PageController'
import OAuthController from './OAuthController'
import LogoutController from './LogoutController'

const Auth = {
    PageController: Object.assign(PageController, PageController),
    OAuthController: Object.assign(OAuthController, OAuthController),
    LogoutController: Object.assign(LogoutController, LogoutController),
}

export default Auth