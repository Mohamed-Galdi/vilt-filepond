<script setup>
import { ref, onMounted, watch, computed } from "vue";
import vueFilePond from "vue-filepond";
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type";
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size";
import FilePondPluginImagePreview from "filepond-plugin-image-preview";
import { setOptions } from "filepond";
import { usePage } from "@inertiajs/vue3";

// Import locales
import ar_AR from "filepond/locale/ar-ar";
import fr_FR from "filepond/locale/fr-fr";
import es_ES from "filepond/locale/es-es";

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  initialFiles: {
    type: Array,
    default: () => [],
  },
  allowedFileTypes: {
    type: Array,
    default: () => [
      "image/jpeg",
      "image/png",
      "image/gif",
      "image/svg+xml",
      "image/webp",
      "image/avif",
    ],
  },
  allowMultiple: {
    type: Boolean,
    default: false,
  },
  maxFiles: {
    type: Number,
    default: 1,
  },
  maxFileSize: {
    type: Number,
    default: 1024 * 1024 * 5, // 5MB
  },
  collection: {
    type: String,
    default: "default",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:modelValue",
  "fileAdded",
  "fileRemoved",
  "error",
]);

// Reactive state
const page = usePage();
const files = ref([]);
const tempFolders = ref([]);
const filePondRef = ref(null);

// Locale configuration
const LOCALE_MAP = {
  ar: ar_AR,
  fr: fr_FR,
  es: es_ES,
  null: null, // English (default)
};

// Initialize FilePond component
const FilePond = vueFilePond(
  FilePondPluginFileValidateType,
  FilePondPluginFileValidateSize,
  FilePondPluginImagePreview
);

// Initialize locale
function initializeLocale() {
  const configuredLocale = page.props.fileUploadConfig?.locale || null;
  const localeOptions = LOCALE_MAP[configuredLocale];

  if (localeOptions) {
    setOptions(localeOptions);
  }
}

// Handle file upload process
function handleProcess(
  fieldName,
  file,
  metadata,
  load,
  error,
  progress,
  abort
) {
  const formData = new FormData();
  formData.append(fieldName, file, file.name);

  if (props.collection) {
    formData.append("collection", props.collection);
  }

  const request = new XMLHttpRequest();

  request.open("POST", route("filepond.upload"));
  request.setRequestHeader("X-CSRF-TOKEN", page.props.csrf_token);
  request.setRequestHeader("Accept", "application/json");

  request.upload.onprogress = (e) => {
    progress(e.lengthComputable, e.loaded, e.total);
  };

  request.onload = () => {
    if (request.status >= 200 && request.status < 300) {
      const folder = parseUploadResponse(request.responseText);

      if (folder) {
        addTempFolder(folder, file);
        load(folder);
      } else {
        error("Invalid response from server");
      }
    } else {
      error("Upload failed");
    }
  };

  request.onerror = () => error("Upload failed");
  request.onabort = () => error("Upload aborted");

  request.send(formData);

  return {
    abort: () => {
      request.abort();
      abort();
    },
  };
}

// Parse upload response (handles both JSON and plain text)
function parseUploadResponse(responseText) {
  try {
    const response = JSON.parse(responseText);
    return typeof response === "string" ? response : response.folder;
  } catch {
    return responseText.trim();
  }
}

// Add temporary folder to state
function addTempFolder(folder, file) {
  tempFolders.value.push(folder);
  emit("fileAdded", { folder, file });
  emit("update:modelValue", [...tempFolders.value]);
}

// Handle file revert (removal of temporary files)
function handleRevert(uniqueId, load, error) {
  if (!uniqueId) {
    error("No unique ID provided");
    return;
  }

  const index = tempFolders.value.indexOf(uniqueId);
  if (index === -1) {
    error("File not found");
    return;
  }

  // Optimistically remove from UI
  tempFolders.value.splice(index, 1);
  emit("update:modelValue", [...tempFolders.value]);

  // Send delete request to server
  fetch(route("filepond.revert", { folder: uniqueId }), {
    method: "DELETE",
    headers: {
      "X-CSRF-TOKEN": page.props.csrf_token,
      Accept: "application/json",
    },
  })
    .then((response) => {
      if (response.ok) {
        emit("fileRemoved", { folder: uniqueId, type: "temp" });
        load();
      } else {
        // Restore folder on server error
        tempFolders.value.splice(index, 0, uniqueId);
        emit("update:modelValue", [...tempFolders.value]);
        error("Failed to delete file from server");
      }
    })
    .catch(() => {
      // Restore folder on network error
      tempFolders.value.splice(index, 0, uniqueId);
      emit("update:modelValue", [...tempFolders.value]);
      error("Failed to delete file");
    });
}

// Handle removal of existing files
function handleFileRemove(error, file) {
  if (error) return;

  // Check if this is a local file (existing file)
  if ((file.origin === 3 || file.origin === 1) && file.source) {
    const existingFile = props.initialFiles.find((f) => f.url === file.source);

    if (existingFile?.id) {
      emit("fileRemoved", {
        fileId: existingFile.id,
        type: "existing",
        file: existingFile,
      });
    }
  }
}

// Public method to reset component state
function resetFiles() {
  files.value = [];
  tempFolders.value = [];
  emit("update:modelValue", []);

  // Clear FilePond instance if available
  if (filePondRef.value) {
    filePondRef.value.removeFiles();
  }
}

// Server configuration for FilePond
const serverOptions = {
  process: handleProcess,
  revert: handleRevert,
  load: (source, load, error) => {
    fetch(source)
      .then((response) => response.blob())
      .then(load)
      .catch(() => error("Could not load file"));
  },
};

// FilePond component options - made reactive with computed
const filePondOptions = computed(() => ({
  server: serverOptions,
  allowMultiple: props.allowMultiple,
  acceptedFileTypes: props.allowedFileTypes,
  maxFiles: props.maxFiles,
  maxFileSize: props.maxFileSize,
  credits: "none",
  disabled: props.disabled,
  required: props.required,
}));

// Watch for external modelValue changes - simplified
watch(
  () => props.modelValue,
  (newValue) => {
    const currentValue = tempFolders.value;
    if (JSON.stringify(newValue) !== JSON.stringify(currentValue)) {
      tempFolders.value = [...(newValue || [])];
    }
  },
  { deep: true }
);

// Initialize component
onMounted(() => {
  initializeLocale();

  // Initialize modelValue
  if (props.modelValue?.length > 0) {
    tempFolders.value = [...props.modelValue];
  }

  // Initialize initial files
  if (props.initialFiles?.length > 0) {
    files.value = props.initialFiles.map((file) => ({
      source: file.url,
      options: { type: "local" },
    }));
  }
});

// Expose public methods
defineExpose({
  resetFiles,
});
</script>

<template>
  <div class="filepond-wrapper">
    <FilePond
      ref="filePondRef"
      v-model="files"
      v-bind="filePondOptions"
      :files="files"
      @removefile="handleFileRemove"
    />
    <input
      v-if="required"
      type="hidden"
      :value="tempFolders.length > 0 ? 'has-files' : ''"
      :required="required"
    />
  </div>
</template>

<style scoped>
.filepond-wrapper {
  @apply w-full;
}

:deep(.filepond--root) {
  @apply font-sans;
}

:deep(.filepond--panel-root) {
  @apply bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg;
}

:deep(.filepond--drop-label) {
  @apply text-gray-600;
}

:deep(.filepond--label-action) {
  @apply text-blue-600 hover:text-blue-700 underline;
}

:deep(.filepond--item-panel) {
  @apply bg-white border border-gray-200 rounded-lg;
}

:deep(.filepond--file-status-main) {
  @apply text-gray-700;
}

:deep(.filepond--file-status-sub) {
  @apply text-gray-500;
}
</style>

<style scoped>
.filepond-wrapper {
  @apply w-full;
}

:deep(.filepond--root) {
  @apply font-sans;
}

:deep(.filepond--panel-root) {
  @apply bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg;
}

:deep(.filepond--drop-label) {
  @apply text-gray-600;
}

:deep(.filepond--label-action) {
  @apply text-blue-600 hover:text-blue-700 underline;
}

:deep(.filepond--item-panel) {
  @apply bg-white border border-gray-200 rounded-lg;
}

:deep(.filepond--file-status-main) {
  @apply text-gray-700;
}

:deep(.filepond--file-status-sub) {
  @apply text-gray-500;
}
</style>
