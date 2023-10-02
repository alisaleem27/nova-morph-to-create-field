<template>
  <div class="border-b border-gray-100 dark:border-gray-700">
    <DefaultField
        :field="currentField"
        :show-errors="false"
        :field-name="fieldName"
        :show-help-text="showHelpText"
        :full-width-content="fullWidthContent"
    >
      <template #field>
        <div v-if="hasMorphToTypes" class="flex relative">
          <select
              :disabled="
              (viaRelatedResource && !shouldIgnoresViaRelatedResource) ||
              currentlyIsReadonly
            "
              :data-testid="`${field.attribute}-type`"
              :dusk="`${field.attribute}-type`"
              :value="resourceType"
              @change="refreshResourcesForTypeChange"
              class="block w-full form-control form-input form-input-bordered form-select mb-3"
          >
            <option value="" selected :disabled="!currentField.nullable">
              {{ __('Choose Type') }}
            </option>

            <option
                v-for="option in currentField.morphToTypes"
                :key="option.value"
                :value="option.value"
                :selected="resourceType == option.value"
            >
              {{ option.singularLabel }}
            </option>
          </select>

          <IconArrow class="pointer-events-none form-select-arrow"/>
        </div>
        <label v-else class="flex items-center select-none mt-2">
          {{ __('There are no available options for this resource.') }}
        </label>
      </template>
    </DefaultField>

    <DefaultField
        :field="currentField"
        :errors="errors"
        :show-help-text="false"
        :field-name="fieldTypeName"
        v-if="hasMorphToTypes"
        :full-width-content="fullWidthContent"
        v-show="resourceType"
    >
      <template #field>
        <div class="flex items-center mb-3">
          <OutlineButton
              type="button"
              v-if="canShowNewRelationModal"
              @click="openRelationModal"
              :dusk="`${field.attribute}-inline-create`"
              v-show="!selectedResource"
          >
            {{ __('Create') }}
          </OutlineButton>
          <div
              v-if="selectedResource"
              class="inline-block pt-2 font-bold">
            <span>{{ selectedResource.display }}</span>
          </div>
        </div>

        <CreateRelationModal
            v-if="canShowNewRelationModal"
            :show="relationModalOpen"
            :size="field.modalSize"
            @set-resource="handleSetResource"
            @create-cancelled="closeRelationModal"
            :resource-name="resourceType"
            :via-relationship="viaRelationship"
            :via-resource="viaResource"
            :via-resource-id="viaResourceId"
        />
      </template>
    </DefaultField>
  </div>
</template>

<script>
import find from 'lodash/find'
import isNil from 'lodash/isNil'
import storage from '@/storage/MorphToFieldStorage'
import {
  DependentFormField,
  HandlesValidationErrors,
  InteractsWithQueryString,
  PerformsSearches,
  TogglesTrashed,
} from '@/mixins'
import DetailField from "@/fields/Detail/MorphToField";
import filled from '@/util/filled'

export default {
  components: {DetailField},
  mixins: [
    DependentFormField,
    HandlesValidationErrors,
    InteractsWithQueryString,
    PerformsSearches,
    TogglesTrashed,
  ],

  data: () => ({
    resourceType: '',
    initializingWithExistingResource: false,
    createdViaRelationModal: false,
    softDeletes: false,
    selectedResourceId: null,
    selectedResource: null,
    search: '',
    relationModalOpen: false,
    withTrashed: false,
  }),

  /**
   * Mount the component.
   */
  mounted() {
    this.initializeComponent()
  },

  methods: {
    initializeComponent() {
      this.selectedResourceId = this.field.value

      if (this.editingExistingResource) {
        this.initializingWithExistingResource = true
        this.resourceType = this.field.morphToType
        this.selectedResourceId = this.field.morphToId
      } else if (this.viaRelatedResource) {
        this.initializingWithExistingResource = true
        this.resourceType = this.viaResource
        this.selectedResourceId = this.viaResourceId
      }

      if (this.shouldSelectInitialResource) {
        if (!this.resourceType && this.field.defaultResource) {
          this.resourceType = this.field.defaultResource
        }
        this.getAvailableResources().then(() => this.selectInitialResource())
      }

      if (this.resourceType) {
        this.determineIfSoftDeletes()
      }

      this.field.fill = this.fill
    },

    /**
     * Set the currently selected resource
     */
    selectResourceFromSearchInput(resource) {
      if (this.field) {
        this.emitFieldValueChange(
            `${this.fieldAttribute}_type`,
            this.resourceType
        )
      }

      this.selectResource(resource)
    },

    /**
     * Select a resource using the <select> control
     */
    selectResourceFromSelectControl(value) {
      this.selectedResourceId = value
      this.selectInitialResource()

      if (this.field) {
        this.emitFieldValueChange(
            `${this.fieldAttribute}_type`,
            this.resourceType
        )
        this.emitFieldValueChange(this.fieldAttribute, this.selectedResourceId)
      }
    },

    /**
     * Fill the forms formData with details from this field
     */
    fill(formData) {
      if (this.selectedResource && this.resourceType) {
        this.fillIfVisible(
            formData,
            this.fieldAttribute,
            this.selectedResource.value
        )
        this.fillIfVisible(
            formData,
            `${this.fieldAttribute}_type`,
            this.resourceType
        )
      } else {
        this.fillIfVisible(formData, this.fieldAttribute, '')
        this.fillIfVisible(formData, `${this.fieldAttribute}_type`, '')
      }

      this.fillIfVisible(
          formData,
          `${this.fieldAttribute}_trashed`,
          this.withTrashed
      )
    },

    /**
     * Get the resources that may be related to this resource.
     */
    getAvailableResources(search = '') {
      Nova.$progress.start()

      return storage
          .fetchAvailableResources(this.resourceName, this.fieldAttribute, {
            params: this.queryParams,
          })
          .then(({data: {resources, softDeletes, withTrashed}}) => {
            Nova.$progress.done()

            if (this.initializingWithExistingResource || !this.isSearchable) {
              this.withTrashed = withTrashed
            }

            if (this.isSearchable) {
              this.initializingWithExistingResource = false
            }
            this.availableResources = resources
            this.softDeletes = softDeletes
          })
          .catch(e => {
            Nova.$progress.done()
          })
    },

    onSyncedField() {
      if (this.resourceType !== this.currentField.morphToType) {
        this.refreshResourcesForTypeChange(this.currentField.morphToType)
      }
    },

    /**
     * Select the initial selected resource
     */
    selectInitialResource() {
      this.selectedResource = find(
          this.availableResources,
          r => r.value == this.selectedResourceId
      )
    },

    /**
     * Determine if the selected resource type is soft deleting.
     */
    determineIfSoftDeletes() {
      return storage
          .determineIfSoftDeletes(this.resourceType)
          .then(({data: {softDeletes}}) => (this.softDeletes = softDeletes))
    },

    /**
     * Handle the changing of the resource type.
     */
    async refreshResourcesForTypeChange(event) {
      this.resourceType = event?.target?.value ?? event
      this.availableResources = []
      this.selectedResource = ''
      this.selectedResourceId = ''
      this.withTrashed = false

      this.softDeletes = false
      this.determineIfSoftDeletes()

      if (!this.isSearchable && this.resourceType) {
        this.getAvailableResources().then(() => {
          this.emitFieldValueChange(
              `${this.fieldAttribute}_type`,
              this.resourceType
          )
          this.emitFieldValueChange(this.fieldAttribute, null)
        })
      }
    },

    /**
     * Toggle the trashed state of the search
     */
    toggleWithTrashed() {
      // Reload the data if the component doesn't have selected resource
      if (!filled(this.selectedResource)) {
        this.withTrashed = !this.withTrashed

        // Reload the data if the component doesn't support searching
        if (!this.isSearchable) {
          this.getAvailableResources()
        }
      }
    },

    openRelationModal() {
      Nova.$emit('create-relation-modal-opened')
      this.relationModalOpen = true
    },

    closeRelationModal() {
      this.relationModalOpen = false
      Nova.$emit('create-relation-modal-closed')
    },

    handleSetResource({id}) {
      this.closeRelationModal()
      this.selectedResourceId = id
      this.createdViaRelationModal = true
      this.initializingWithExistingResource = true
      this.getAvailableResources().then(() => {
        this.selectInitialResource()

        this.emitFieldValueChange(
            `${this.fieldAttribute}_type`,
            this.resourceType
        )
        this.emitFieldValueChange(this.fieldAttribute, this.selectedResourceId)
      })
    },

    performResourceSearch(search) {
      if (this.useSearchInput) {
        this.performSearch(search)
      } else {
        this.search = search
      }
    },

    clearResourceSelection() {
      this.clearSelection()

      if (this.viaRelatedResource && !this.createdViaRelationModal) {
        this.updateQueryString({
          viaResource: null,
          viaResourceId: null,
          viaRelationship: null,
          relationshipType: null,
        }).then(() => {
          Nova.$router.reload({
            onSuccess: () => {
              this.initializingWithExistingResource = false
              this.initializeComponent()
            },
          })
        })
      } else {
        if (this.createdViaRelationModal) {
          this.createdViaRelationModal = false
          this.initializingWithExistingResource = false
        }

        this.getAvailableResources()
      }
    },
  },

  computed: {
    /**
     * Determine if an existing resource is being updated.
     */
    editingExistingResource() {
      return Boolean(this.field.morphToId && this.field.morphToType)
    },

    /**
     * Determine if we are creating a new resource via a parent relation
     */
    viaRelatedResource() {
      return Boolean(
          find(
              this.currentField.morphToTypes,
              type => type.value == this.viaResource
          ) &&
          this.viaResource &&
          this.viaResourceId &&
          this.currentField.reverse
      )
    },

    /**
     * Determine if we should select an initial resource when mounting this field
     */
    shouldSelectInitialResource() {
      return Boolean(
          this.editingExistingResource ||
          this.viaRelatedResource ||
          Boolean(this.field.value && this.field.defaultResource)
      )
    },

    /**
     * Determine if the related resources is searchable
     */
    isSearchable() {
      return Boolean(this.currentField.searchable)
    },

    shouldLoadFirstResource() {
      return (
          ((this.useSearchInput &&
                  !this.shouldIgnoreViaRelatedResource &&
                  this.shouldSelectInitialResource) ||
              this.createdViaRelationModal) &&
          this.initializingWithExistingResource
      )
    },

    /**
     * Get the query params for getting available resources
     */
    queryParams() {
      return {
        type: this.resourceType,
        current: this.selectedResourceId,
        first: this.shouldLoadFirstResource,
        search: this.search,
        withTrashed: this.withTrashed,
        viaResource: this.viaResource,
        viaResourceId: this.viaResourceId,
        viaRelationship: this.viaRelationship,
        component: this.field.dependentComponentKey,
        dependsOn: this.encodedDependentFieldValues,
        editing: true,
        editMode:
            isNil(this.resourceId) || this.resourceId === ''
                ? 'create'
                : 'update',
      }
    },

    /**
     * Return the morphable type label for the field
     */
    fieldName() {
      return this.field.name
    },

    /**
     * Return the selected morphable type's label
     */
    fieldTypeName() {
      if (this.resourceType) {
        return (
            find(this.currentField.morphToTypes, type => {
              return type.value == this.resourceType
            })?.singularLabel || ''
        )
      }

      return ''
    },

    /**
     * Determine whether there are any morph to types.
     */
    hasMorphToTypes() {
      return this.currentField.morphToTypes.length > 0
    },

    authorizedToCreate() {
      return find(Nova.config('resources'), resource => {
        return resource.uriKey == this.resourceType
      }).authorizedToCreate
    },

    canShowNewRelationModal() {
      return (
          this.currentField.showCreateRelationButton &&
          this.resourceType &&
          !this.shownViaNewRelationModal &&
          !this.viaRelatedResource &&
          !this.currentlyIsReadonly &&
          this.authorizedToCreate
      )
    },

    shouldShowTrashed() {
      return (
          this.softDeletes &&
          !this.viaRelatedResource &&
          !this.currentlyIsReadonly &&
          this.currentField.displaysWithTrashed
      )
    },

    currentFieldValues() {
      return {
        [this.fieldAttribute]: this.value,
        [`${this.fieldAttribute}_type`]: this.resourceType,
      }
    },

    /**
     * Return the field options filtered by the search string.
     */
    filteredResources() {
      if (!this.isSearchable) {
        return this.availableResources.filter(option => {
          return (
              option.display.toLowerCase().indexOf(this.search.toLowerCase()) >
              -1 || new String(option.value).indexOf(this.search) > -1
          )
        })
      }

      return this.availableResources
    },

    shouldIgnoresViaRelatedResource() {
      return this.viaRelatedResource && filled(this.search)
    },

    useSearchInput() {
      return this.isSearchable || this.viaRelatedResource
    },
  },
}
</script>
