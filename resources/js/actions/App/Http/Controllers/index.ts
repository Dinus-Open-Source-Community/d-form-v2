import Dashboard from './Dashboard'
import Auth from './Auth'
import FeaturePageController from './FeaturePageController'
import EventsController from './EventsController'
import DocsPageController from './DocsPageController'

const Controllers = {
    Dashboard: Object.assign(Dashboard, Dashboard),
    Auth: Object.assign(Auth, Auth),
    FeaturePageController: Object.assign(FeaturePageController, FeaturePageController),
    EventsController: Object.assign(EventsController, EventsController),
    DocsPageController: Object.assign(DocsPageController, DocsPageController),
}

export default Controllers