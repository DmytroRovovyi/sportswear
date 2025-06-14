<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8" />
    <title>Sportswear</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold mb-6">Catalog sportswear list</h1>

    <div class="flex">
        <div class="w-1/6 md:w-1/4">
            <form method="GET" action="{{ route('catalog.index') }}" class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold mb-4">Filters</h2>

                @foreach ($filters as $slug => $options)
                    <div x-data="{ open: false }" class="mb-4 border-b pb-2">
                        <button type="button"
                                @click="open = !open"
                                class="w-full text-left font-medium text-gray-800 flex justify-between items-center">
                            {{ ucfirst($slug) }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24">
                                <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8.009 8.009 0 0 1-8 8z"/>
                                <path d="M12 12.586 8.707 9.293l-1.414 1.414L12 15.414l4.707-4.707-1.414-1.414L12 12.586z"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="mt-2 pl-2">
                            @foreach ($options as $option)
                                <label class="block text-sm">
                                    <input type="checkbox" name="{{ $slug }}[]" value="{{ $option['value'] }}"
                                           {{ in_array($option['value'], request()->input($slug, [])) ? 'checked' : '' }}
                                           class="mr-2">
                                    {{ $option['value'] }} ({{ $option['count'] }})
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 mt-4">
                    Select filters
                </button>
            </form>
        </div>

        <div class="w-5/6 md:w-3/4">
            <h2 class="text-xl font-semibold mb-4">Знайдено: {{ $total }} товарів</h2>

            @forelse ($products as $product)
                <div class="bg-white p-4 rounded shadow mb-4">
                    <h3 class="text-lg font-bold">{{ $product->name }}</h3>
                    <p><strong>Виробник:</strong> {{ $product->vendor }}</p>
                    <p><strong>Категорія:</strong> {{ $product->category_id }}</p>
                    <p><strong>Ціна:</strong> {{ $product->price }}</p>
                    <p><strong>Опис:</strong> {{ $product->description }}</p>
                </div>
            @empty
                <p>Немає товарів за вибраними фільтрами.</p>
            @endforelse

            <div class="mt-6 flex justify-center items-center flex-col text-center">
                <p class="mb-2">Сторінка {{ $currentPage }} з {{ $lastPage }}</p>
                <div class="space-x-4">
                    @if ($currentPage > 1)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}"
                           class="text-blue-600 hover:underline">← prev</a>
                    @endif

                    @if ($currentPage < $lastPage)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}"
                           class="text-blue-600 hover:underline">next →</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
