import FormController from './FormController'
import FieldOperationController from './FieldOperationController'

const Forms = {
    FormController: Object.assign(FormController, FormController),
    FieldOperationController: Object.assign(FieldOperationController, FieldOperationController),
}

export default Forms