import IndexField from '@/fields/Index/MorphToField'
import DetailField from '@/fields/Detail/MorphToField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
    app.component('index-morph-to-create-field', IndexField)
    app.component('detail-morph-to-create-field', DetailField)
    app.component('form-morph-to-create-field', FormField)
})
