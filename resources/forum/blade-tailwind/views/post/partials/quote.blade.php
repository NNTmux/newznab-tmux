<div class="bg-white dark:bg-gray-800 rounded-md border dark:border-gray-700 mb-2 transition-colors">
    <div class="p-6">
        <div class="flex justify-between flex-row-reverse mb-2">
            <span>
                <a href="{{ Forum::route('thread.show', $post) }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">#{{ $post->sequence }}</a>
            </span>
            <div>
                <div>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $post->authorName }}</strong>
                    <span class="text-gray-500 dark:text-gray-400">{{ $post->posted }}</span>
                </div>
                @if ($post->author && $post->author->role)
                    <div class="mt-1">
                        @php
                            $roleName = $post->author->role->name;
                            $roleColors = [
                                'Admin' => 'bg-red-500 dark:bg-red-600 text-white',
                                'Moderator' => 'bg-green-500 dark:bg-green-600 text-white',
                                'Friend' => 'bg-purple-500 dark:bg-purple-600 text-white',
                                'User' => 'bg-blue-500 dark:bg-blue-600 text-white',
                                'Disabled' => 'bg-gray-500 dark:bg-gray-600 text-white',
                            ];
                            $roleClass = $roleColors[$roleName] ?? 'bg-gray-400 dark:bg-gray-500 text-white';
                        @endphp
                        <span class="inline-block text-xs font-medium px-2 py-1 rounded {{ $roleClass }}">
                            {{ $roleName }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
        <div class="text-gray-900 dark:text-gray-100">
            {!! \Illuminate\Support\Str::limit(Forum::render($post->content)) !!}
        </div>
    </div>
</div>
