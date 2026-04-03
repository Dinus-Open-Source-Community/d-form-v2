import FeaturePageController from './FeaturePageController'
import EventsController from './EventsController'
import DocsPageController from './DocsPageController'
import Dashboard from './Dashboard'
import Auth from './Auth'

const Controllers = {
    FeaturePageController: Object.assign(FeaturePageController, FeaturePageController),
    EventsController: Object.assign(EventsController, EventsController),
    DocsPageController: Object.assign(DocsPageController, DocsPageController),
    Dashboard: Object.assign(Dashboard, Dashboard),
    Auth: Object.assign(Auth, Auth),
}

export default Controllers