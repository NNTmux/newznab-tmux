@extends ('forum::layouts.main', ['category' => null, 'thread' => null, 'breadcrumbs_append' => [trans('forum::general.manage')]])

@section ('content')
    <div class="flex flex-row justify-between mb-2">
        <h2 class="text-3xl font-medium my-3 text-gray-900 dark:text-gray-100">{{ trans('forum::general.manage') }}</h2>

        @can ('createCategories')
            <x-forum::button type="button" data-open-modal="create-category" class="px-6">
                {{ trans('forum::categories.create') }}
            </x-forum::button>

            @include ('forum::category.modals.create')
        @endcan
    </div>

    <div id="manage-categories">
        <draggable-category-list :categories="state.categories"></draggable-category-list>

        <transition name="fade">
            <div v-show="state.changesApplied" class="bg-green-100 dark:bg-green-900/30 mb-4 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800 mt-3 px-4 py-3 rounded transition-colors" role="alert">
                {{ trans('forum::general.changes_applied') }}
            </div>
        </transition>

        <div class="flex justify-end py-3">
            <button type="button" class="bg-blue-500 dark:bg-blue-600 text-white rounded py-2 px-8 hover:cursor-pointer hover:bg-blue-400 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors" :disabled="state.isSavingDisabled" @click="onSave">
                {{ trans('forum::general.save') }}
            </button>
        </div>
    </div>

    <script type="text/x-template" id="draggable-category-list-template">
        <draggable
            :list="categories"
            tag="ul"
            class="cursor-move"
            @start="drag=true"
            @end="drag=false"
            :group="{ name: 'categories' }"
            :empty-insert-threshold="50"
            item-key="id">
            <template #item="{element}">
                <li class="bg-white dark:bg-gray-800 p-4 my-2 rounded-md border dark:border-gray-700 transition-colors" :data-id="element.id">
                    <span class="flex">
                        <span class="grow">
                            <strong class="text-gray-900 dark:text-gray-100">@{{ element.title }}</strong>
                            <div class="text-muted text-gray-500 dark:text-gray-400">@{{ element.description }}</div>
                        </span>
                        <span>
                            <a class="text-blue-500 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 px-3 py-2 text-sm ml-2 transition-colors" :href="element.route + '#modal=edit-category'">{{ trans('forum::general.edit') }}</a>
                            <a class="bg-red-500 dark:bg-red-600 hover:bg-red-400 dark:hover:bg-red-500 text-white hover:text-white px-3 py-2 text-sm rounded ml-2 transition-colors" :href="element.route + '#modal=delete-category'">{{ trans('forum::general.delete') }}</a>
                        </span>
                    </span>

                    <span v-if="element.children.length == 0" class="block min-h-5 border border-dashed border-slate-400 dark:border-slate-600 rounded">
                        <draggable-category-list :categories="element.children" />
                    </span>
                    <draggable-category-list v-else :categories="element.children" />
                </li>
            </template>
        </draggable>
    </script>

    <script type="module">
    const app = Vue.createApp({
        setup() {
            const state = Vue.reactive({
                categories: @json($categories),
                isSavingDisabled: true,
                changesApplied: false,
            });

            Vue.watch(
                () => state.categories,
                async (newValue, oldValue) => {
                    state.isSavingDisabled = false;
                },
                { deep: true }
            );

            function onSave()
            {
                state.isSavingDisabled = true;
                state.changesApplied = false;

                var payload = { categories: state.categories };
                axios.post('{{ route('forum.bulk.category.manage') }}', payload)
                    .then(response => {
                        state.changesApplied = true;
                        setTimeout(() => state.changesApplied = false, 3000);
                    })
                    .catch(error => {
                        state.isSavingDisabled = false;
                        console.log(error);
                    });
            }

            return {
                state,
                onSave
            };
        }
    });

    app.component(
        'Draggable',
        VueDraggable
    );

    app.component(
        'DraggableCategoryList',
        {
            props: ['categories'],
            template: '#draggable-category-list-template',
        }
    );

    app.mount('#manage-categories');
    </script>
@stop
