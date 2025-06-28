# Laravel FilePond Package for VILT Stack

A comprehensive Laravel package for handling file uploads using FilePond in VILT (Vue, Inertia, Laravel, Tailwind) stack applications.

## Features

- 🚀 **Easy Integration** - Seamless FilePond integration with Laravel
- 📁 **Flexible Uploads** - Support for single and multiple file uploads
- 🔄 **Smart Management** - Temporary file handling with automatic cleanup
- 🏷️ **Collections** - Organize files using collections (images, documents, etc.)
- 📱 **Responsive Design** - Mobile-friendly Vue component
- 🎨 **Styled Components** - Pre-styled with Tailwind CSS
- 🔒 **Security First** - Built-in validation and security features
- 📊 **Polymorphic Relations** - Works with any Eloquent model
- 🌐 **Multi-language** - Supports multiple locales (Arabic, French, Spanish, English)

## Requirements

- Laravel 11.0 or higher
- VILT stack (Vue.js, Inertia.js, Laravel, Tailwind CSS)
- PHP 8.1 or higher

## Installation

### 1. Install the Package

```bash
composer require mohamedgaldi/vilt-filepond
```

### 2. Install Frontend Dependencies

```bash
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

### 4. Import FilePond Styles

Add the following imports to your `app.css` file **before** your Tailwind directives:

```css
@import "filepond/dist/filepond.min.css";
@import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css";

@tailwind base;
@tailwind components;
@tailwind utilities;
```

⚠️ **Important**: The FilePond CSS imports must come before Tailwind directives to ensure proper styling.

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Configure Storage

Ensure your **public** disk is properly configured in `config/filesystems.php` and create the storage link:

```bash
php artisan storage:link
```

### 7. Environment Configuration

#### APP_URL Configuration

Ensure your `APP_URL` in `.env` matches your development server URL:

```env
# ❌ Wrong - will cause image loading issues
APP_URL=http://localhost

# ✅ Correct - matches your actual server URL
APP_URL=http://127.0.0.1:8000
```

### 8. Configure Inertia Middleware

Add the CSRF token and config values to your `HandleInertiaRequests` middleware:

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

## Quick Start

### 1. Prepare Your Model

Add the `HasFiles` trait to any model that needs file uploads:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class Product extends Model
{
    use HasFiles;

    protected $fillable = ['name', 'description'];
}
```

### 2. Create a New Record with Files

```vue
<script setup>
import { useForm } from "@inertiajs/vue3";
import FileUpload from "@/Components/ViltFilePond/FileUpload.vue";
import { ref } from "vue";

const form = useForm({
    name: "",
    images: [],
});

const fileUploadRef = ref(null);

function handleSubmit() {
    form.post(route("products.store"), {
        onSuccess: () => {
            form.reset();
            fileUploadRef.value?.resetFiles();
        },
    });
}
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Name</label>
            <input
                v-model="form.name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300"
                required
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
            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 disabled:opacity-50"
        >
            {{ form.processing ? "Saving..." : "Create Product" }}
        </button>
    </form>
</template>
```

### 3. Update Existing Records

```vue
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

// Sync file changes with form
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
    <form @submit.prevent="handleSubmit">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Name</label>
            <input
                v-model="form.name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300"
                required
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

        <button
            type="submit"
            :disabled="form.processing"
            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 disabled:opacity-50"
        >
            {{ form.processing ? "Updating..." : "Update Product" }}
        </button>
    </form>
</template>
```

### 4. Handle Files in Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class ProductController extends Controller
{
    protected FilePondService $filePondService;

    public function __construct(FilePondService $filePondService)
    {
        $this->filePondService = $filePondService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'images' => 'array',
            'images.*' => 'string',
        ]);

        $product = Product::create($request->only(['name']));

        // Handle file uploads
        if ($request->has('images') && !empty($request->images)) {
            $this->filePondService->handleFileUploads(
                $product, 
                $request->images, 
                'images'
            );
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully!');
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'images_temp_folders' => 'array',
            'images_removed_files' => 'array',
        ]);

        $product->update($request->only(['name']));

        // Handle file updates
        $this->filePondService->handleFileUpdates(
            $product,
            $request->input('images_temp_folders', []),
            $request->input('images_removed_files', []),
            'images'
        );

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully!');
    }
}
```

## Component Props

### FileUpload Component

| Prop | Type | Default | Description |
| --- | --- | --- | --- |
| `modelValue` | Array | `[]` | Array of temporary folder names |
| `initialFiles` | Array | `[]` | Array of existing files to display |
| `allowedFileTypes` | Array | Image types | Accepted MIME types |
| `allowMultiple` | Boolean | `false` | Allow multiple file uploads |
| `maxFiles` | Number | `1` | Maximum number of files |
| `maxFileSize` | String | `"10MB"` | Maximum file size |
| `collection` | String | `"default"` | File collection name |
| `disabled` | Boolean | `false` | Disable the component |
| `required` | Boolean | `false` | Mark as required field |

### Events

| Event | Payload | Description |
| --- | --- | --- |
| `@file-added` | `{ folder, file }` | Fired when a file is uploaded |
| `@file-removed` | `{ fileId, type, file }` | Fired when a file is removed |
| `@error` | `error` | Fired when an error occurs |

## useFilePond Composable

The composable provides reactive state management for file operations:

```javascript
const {
    // State
    files,              // Existing files
    tempFolders,        // Temporary uploaded files
    removedFileIds,     // IDs of removed files
    errors,             // Error messages
    isSubmitting,       // Submission status

    // Computed
    hasFiles,           // Whether there are any files
    totalFiles,         // Total file count
    hasChanges,         // Whether there are pending changes
    hasErrors,          // Whether there are errors

    // Methods
    handleFileAdded,    // Handle file addition
    handleFileRemoved,  // Handle file removal
    handleError,        // Handle errors
    resetState,         // Reset all state
    submitFiles,        // Submit files to server
} = useFilePond(initialFiles, collection);
```

## Configuration

Customize the package behavior in `config/vilt-filepond.php`:

```php
return [
    // Storage configuration
    'storage_disk' => env('FILEPOND_STORAGE_DISK', 'public'),
    'temp_path' => 'temp-files',
    'files_path' => 'files',

    // File validation
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/svg+xml',
        'image/webp',
        'image/avif',
    ],
    'max_file_size' => 10 * 1024 * 1024, // 10MB

    // Localization
    'locale' => env('FILEPOND_LOCALE', null), // null = English

    // Cleanup settings
    'cleanup_temp_files' => true,
    'temp_file_lifetime' => 24, // hours
];
```

## API Reference

### HasFiles Trait Methods

| Method | Description |
| --- | --- |
| `files()` | Get all files relationship |
| `getFilesByCollection(string $collection)` | Get files by collection |
| `getFirstFile(string $collection = 'default')` | Get first file from collection |
| `hasFiles(string $collection = null)` | Check if model has files |
| `deleteFiles(string $collection = null)` | Delete files by collection |

### FilePondService Methods

| Method | Description |
| --- | --- |
| `storeTempFile(UploadedFile $file, string $collection)` | Store temporary file |
| `moveTempFileToModel($model, string $folder, string $collection, int $order)` | Move temp file to model |
| `handleFileUploads($model, array $tempFolders, string $collection)` | Handle multiple uploads |
| `handleFileUpdates($model, array $tempFolders, array $removedFiles, string $collection)` | Handle file updates |
| `cleanupTempFiles()` | Clean up old temporary files |

## Troubleshooting

### Common Issues

1. **Images not loading**: Check your `APP_URL` in `.env` matches your server URL
2. **Styles not working**: Ensure FilePond CSS is imported before Tailwind
3. **Upload failures**: Verify CSRF token is shared via Inertia middleware
4. **Permission errors**: Run `php artisan storage:link` and check file permissions

### Debug Mode

Enable debug mode in your config file to get detailed error information:

```php
'debug' => env('FILEPOND_DEBUG', false),
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

**Made with ❤️ for the VILT Stack community**