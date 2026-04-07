import OAuthController from './OAuthController'
import LogoutController from './LogoutController'

const Auth = {
    OAuthController: Object.assign(OAuthController, OAuthController),
    LogoutController: Object.assign(LogoutController, LogoutController),
}

export default Auth