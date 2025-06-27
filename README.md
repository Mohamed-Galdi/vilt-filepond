# Laravel FilePond Package

A comprehensive Laravel package for handling file uploads using FilePond in VILT (Vue, Inertia, Laravel, Tailwind) stack applications.

## Features

- 🚀 Easy integration with FilePond
- 📁 Support for single and multiple file uploads
- 🔄 Temporary file handling with automatic cleanup
- 🏷️ File collections for organizing different types of files
- 📱 Responsive Vue component
- 🎨 Tailwind CSS styling
- 🔒 Built-in validation and security
- 📊 Polymorphic relationships for any model

## Installation

### 1. Install the Package

```bash
composer require mohamedgaldi/vilt-filepond
```

### 2. Install Frontend Dependencies

```
npm install filepond vue-filepond filepond-plugin-file-validate-type filepond-plugin-file-validate-size filepond-plugin-image-preview
```

### 3. Publish Package Assets

```bash
# Publish everything at once
php artisan vendor:publish --tag=vilt-filepond

# Or publish individual assets
php artisan vendor:publish --tag=vilt-filepond-config
php artisan vendor:publish --tag=vilt-filepond-migrations
php artisan vendor:publish --tag=vilt-filepond-vue
```

### 4. FilePond Styles

Add those two lines to your **app.css** (or **app.js**) file:

```css
@import "filepond/dist/filepond.min.css";
@import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css";
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Configure Storage

Make sure your **public** disk is properly configured in **config/filesystems.php** and run:

```bash
php artisan storage:link
```

### 6. Configure CSRF Token and Config Values (Important!)

Make sure to add the CSRF token and any required config values to the share method of your `HandleInertiaRequests` middleware. This ensures they are available to your frontend via Inertia:

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'user' => fn () => $request->user()
            ? $request->user()->only('name', 'email')
            : null,
        'csrf_token' => csrf_token(),
        'fileUploadConfig' => [
                'locale' => config('vilt-filepond.locale'),
            ],
    ];
}
```

## Usage

### 1. Prepare Your Model

Add the `HasFiles` trait to any model that needs file uploads:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;


class Post extends Model
{
    use HasFiles;

    protected $fillable = ['title', 'content'];
}
```

### 2. Use in Vue Components

Basic Single File Upload

```html
<script setup>
  import { useForm, router } from "@inertiajs/vue3";
  import FileUpload from "@/Components/ViltFilePond/FileUpload.vue";
  import { ref } from "vue";

  const props = defineProps({
    products: Object,
  });

  const form = useForm({
    name: "",
    images: [],
  });

  const fileUploadRef = ref(null);

  function handleSubmit() {
    form.post(route("products.store"), {
      onSuccess: () => {
        form.reset();
        // Reset the FileUpload component
        fileUploadRef.value?.resetFiles();
      },
    });
  }

  function handleEdit(id) {
    router.get(route("products.edit", { id: id }));
  }

  function handleDelete(id) {
    router.delete(route("products.destroy", { id: id }));
  }
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-2xl">
    <form @submit.prevent="handleSubmit">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Title</label>
        <input
          v-model="form.name"
          type="text"
          class="mt-1 block w-full rounded-md border-gray-300"
        />
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Images</label>
        <FileUpload
          ref="fileUploadRef"
          v-model="form.images"
          :allow-multiple="true"
          :max-files="5"
          collection="images"
        />
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="bg-blue-500 text-white px-4 py-2 rounded"
      >
        {{ form.processing ? "Saving..." : "Save Post" }}
      </button>
    </form>
    <div class="grid grid-cols-3 gap-4 my-8">
      <div
        v-if="products.length > 0"
        v-for="product in props.products"
        class="bg-slate-200 p-2 rounded-md"
      >
        <p>{{ product.name }}</p>
        <div v-for="image in product.files">
          <img :src="image.url" alt="Image" />
        </div>
        <div class="flex justify-end gap-4">
          <button
            class="bg-slate-700 hover:bg-yellow-500 text-white p-1 text-sm mt-2 rounded w-1/2"
            @click="handleEdit(product.id)"
          >
            Edit
          </button>
          <button
            class="bg-slate-700 hover:bg-red-500 text-white p-1 text-sm mt-2 rounded w-1/2"
            @click="handleDelete(product.id)"
          >
            Delete
          </button>
        </div>
      </div>
      <div v-else>
        <p>No products found</p>
      </div>
    </div>
  </div>
</template>
```

Update page using the composable

```html
<script setup>
  import { useForm } from "@inertiajs/vue3";
  import { watch } from "vue";
  import FileUpload from "@/Components/ViltFilePond/FileUpload.vue";
  import { useFilePond } from "@/Composables/ViltFilePond/useFilePond.js";

  const props = defineProps({
    product: Object,
  });

  // Initialize file management
  const {
    files,
    tempFolders,
    removedFileIds,
    handleFileAdded,
    handleFileRemoved,
    handleError,
  } = useFilePond(props.product?.files || [], "images");

  const form = useForm({
    name: props.product?.name || "",
    images_temp_folders: [],
    images_removed_files: [],
  });

  // Watch for changes and update form reactively
  watch(
    [tempFolders, removedFileIds],
    () => {
      form.images_temp_folders = [...tempFolders.value];
      form.images_removed_files = [...removedFileIds.value];
    },
    { deep: true, immediate: true }
  );

  function handleSubmit() {
    form.put(route("products.update", props.product.id));
  }
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-2xl">
    <form @submit.prevent="handleSubmit">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Name</label>
        <input
          v-model="form.name"
          type="text"
          class="mt-1 block w-full rounded-md border-gray-300"
        />
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Images</label>
        <FileUpload
          v-model="tempFolders"
          :initial-files="files"
          :allow-multiple="true"
          :max-files="5"
          collection="images"
          @file-added="handleFileAdded"
          @file-removed="handleFileRemoved"
          @error="handleError"
        />
      </div>

      <button type="submit" :disabled="form.processing">
        {{ form.processing ? "Saving..." : "Save product" }}
      </button>
    </form>
  </div>
</template>
```

### 3. Handle in Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class PostController extends Controller
{
    protected FilePondService $filePondService;

    public function __construct(FilePondService $filePondService)
    {
        $this->filePondService = $filePondService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'images' => 'array',
            'images.*' => 'string', // These are temp folder names
        ]);

        $post = Post::create($request->only(['title', 'content']));

        // Handle file uploads
        if ($request->has('images') && !empty($request->images)) {
            $this->filePondService->handleFileUploads($post, $request->images, 'images');
        }

        return redirect()->route('posts.index')->with('success', 'Post created successfully!');
    }

    public function update(Request $request, Post $post)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'images_temp_folders' => 'array',
            'images_removed_files' => 'array',
        ]);

        $post->update($request->only(['title', 'content']));

        // Handle file updates
        $this->filePondService->handleFileUpdates(
            $post,
            $request->input('images_temp_folders', []),
            $request->input('images_removed_files', []),
            'images'
        );

        return redirect()->route('posts.index')->with('success', 'Post updated successfully!');
    }

    public function show(Post $post)
    {
        $post->load('files');

        return inertia('Posts/Show', [
            'post' => $post
        ]);
    }
}
```

## Configuration

You can customize the package behavior by modifying the published config file at config/filepond.php:

```php
return [
    'storage_disk' => 'public',
    'temp_path' => 'temp-files',
    'files_path' => 'files',
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        // Add more types...
    ],
    'max_file_size' => 10 * 1024 * 1024, // 10MB
];
```

## API Reference

### `HasFiles` Trait Methods

- `files()` - Get all files relationship
- `getFilesByCollection(string $collection)` - Get files by collection
- `getFirstFile(string $collection)` - Get first file from collection
- `hasFiles(string $collection)` - Check if model has files in collection
- `deleteFiles(string $collection)` - Delete all files in collection

### FilePondService Methods

- `storeTempFile(UploadedFile $file)` - Store temporary file
- `moveTempFileToModel($model, string $folder, string $collection, int $order)` - Move temp file to model
- `handleFileUploads($model, array $tempFolders, string $collection)` - Handle multiple file uploads
- `handleFileUpdates($model, array $tempFolders, array $removedFiles, string $collection)` - Handle file updates

# License

This package is open-sourced software licensed under the MIT license.
