@if ($category->parent !== null)
    @include ('forum::partials.breadcrumb-categories', ['category' => $category->parent])
@endif
<li class=""><a href="{{ Forum::route('category.show', $category) }}" class="text-blue-500 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">{{ $category->title }}</a></li>
