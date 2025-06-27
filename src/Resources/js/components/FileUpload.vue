<script setup>
import { ref, onMounted, watch, nextTick } from "vue";
import vueFilePond from "vue-filepond";
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type";
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size";
import FilePondPluginImagePreview from "filepond-plugin-image-preview";
import { setOptions } from "filepond";
import ar_AR from "filepond/locale/ar-ar";
import fr_FR from "filepond/locale/fr-fr";
import es_ES from "filepond/locale/es-es";
import { usePage } from "@inertiajs/vue3";

// Define component props
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
            "application/pdf",
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
        type: String,
        default: "10MB",
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

// Define emits
const emit = defineEmits([
    "update:modelValue",
    "fileAdded",
    "fileRemoved",
    "error",
]);

// Reactive variables
const page = usePage();
const files = ref([]);
const tempFolders = ref([]);
const isUpdating = ref(false);

const localeMap = {
    ar: ar_AR,
    fr: fr_FR,
    es: es_ES,
    null: null, // English (default)
};
const configuredLocale = page.props.fileUploadConfig?.locale || null;

// Apply locale settings
const applyLocale = (locale) => {
    const localeOptions = localeMap[locale];
    if (localeOptions) {
        setOptions(localeOptions);
    }
    // If locale is null or not found, FilePond will use English (default)
};

// Apply the configured locale
applyLocale(configuredLocale);

// Create FilePond component
const FilePond = vueFilePond(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview
);

// Initialize component
onMounted(() => {
    if (props.modelValue && props.modelValue.length > 0) {
        tempFolders.value = [...props.modelValue];
    }

    if (props.initialFiles && props.initialFiles.length > 0) {
        files.value = props.initialFiles.map((file) => ({
            source: file.url,
            options: { type: "local" },
        }));
    }
});

// Watch for changes in modelValue prop (parent to child)
watch(
    () => props.modelValue,
    (newValue) => {
        if (isUpdating.value) return;

        const currentValue = tempFolders.value;
        if (JSON.stringify(newValue) !== JSON.stringify(currentValue)) {
            tempFolders.value = [...(newValue || [])];
        }
    },
    { deep: true }
);

// Watch for changes in tempFolders and emit updates (child to parent)
watch(
    tempFolders,
    (newValue) => {
        if (isUpdating.value) return;

        isUpdating.value = true;
        nextTick(() => {
            emit("update:modelValue", [...newValue]);
            nextTick(() => {
                isUpdating.value = false;
            });
        });
    },
    { deep: true }
);

// Handle file upload process
function handleProcess(
    fieldName,
    file,
    metadata,
    load,
    error,
    progress,
    abort,
    transfer,
    options
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

    request.onload = function () {
        if (request.status >= 200 && request.status < 300) {
            try {
                const response = JSON.parse(request.responseText);
                const folder =
                    typeof response === "string" ? response : response.folder;

                if (folder) {
                    isUpdating.value = true;
                    tempFolders.value.push(folder);

                    nextTick(() => {
                        emit("fileAdded", { folder, file });
                        emit("update:modelValue", [...tempFolders.value]);

                        nextTick(() => {
                            isUpdating.value = false;
                        });
                    });

                    load(folder);
                } else {
                    error("Invalid response from server");
                }
            } catch (e) {
                const folder = request.responseText.trim();

                if (folder) {
                    isUpdating.value = true;
                    tempFolders.value.push(folder);

                    nextTick(() => {
                        emit("fileAdded", { folder, file });
                        emit("update:modelValue", [...tempFolders.value]);

                        nextTick(() => {
                            isUpdating.value = false;
                        });
                    });

                    load(folder);
                } else {
                    error("Invalid response from server");
                }
            }
        } else {
            error("Upload failed");
        }
    };

    request.onerror = function () {
        error("Upload failed");
    };

    request.onabort = function () {
        error("Upload aborted");
    };

    request.send(formData);

    return {
        abort: () => {
            request.abort();
            abort();
        },
    };
}

// Handle file revert (removal of temporary files)
function handleRevert(uniqueId, load, error) {
    try {
        if (!uniqueId) {
            error("No unique ID provided");
            return;
        }

        const index = tempFolders.value.indexOf(uniqueId);
        if (index > -1) {
            isUpdating.value = true;
            tempFolders.value.splice(index, 1);

            nextTick(() => {
                emit("update:modelValue", [...tempFolders.value]);

                nextTick(() => {
                    isUpdating.value = false;
                });
            });
        }

        fetch(route("filepond.revert", { folder: uniqueId }), {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": page.props.csrf_token,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        })
            .then((response) => {
                if (response.ok) {
                    emit("fileRemoved", { folder: uniqueId, type: "temp" });
                    load();
                } else {
                    if (index > -1) {
                        isUpdating.value = true;
                        tempFolders.value.splice(index, 0, uniqueId);

                        nextTick(() => {
                            emit("update:modelValue", [...tempFolders.value]);

                            nextTick(() => {
                                isUpdating.value = false;
                            });
                        });
                    }
                    error("Failed to delete file from server");
                }
            })
            .catch((err) => {
                if (index > -1) {
                    isUpdating.value = true;
                    tempFolders.value.splice(index, 0, uniqueId);

                    nextTick(() => {
                        emit("update:modelValue", [...tempFolders.value]);

                        nextTick(() => {
                            isUpdating.value = false;
                        });
                    });
                }
                error("Failed to delete file");
            });
    } catch (e) {
        error("Failed to delete file");
    }
}

// Handle removal of existing files
function handleFileRemove(error, file) {
    if (error) {
        return;
    }

    if ((file.origin === 3 || file.origin === 1) && file.source) {
        const existingFile = props.initialFiles.find(
            (f) => f.url === file.source
        );

        if (existingFile && existingFile.id) {
            emit("fileRemoved", {
                fileId: existingFile.id,
                type: "existing",
                file: existingFile,
            });
        }
    }
}

// Reset files and tempFolders
function resetFiles() {
    files.value = [];
    tempFolders.value = [];
    emit("update:modelValue", []);
}

// FilePond server configuration
const serverOptions = {
    process: handleProcess,
    revert: handleRevert,
    load: (source, load, error) => {
        fetch(source)
            .then((response) => response.blob())
            .then(load)
            .catch(() => {
                error("Could not load file");
            });
    },
};

// FilePond options
const filePondOptions = {
    server: serverOptions,
    allowMultiple: props.allowMultiple,
    acceptedFileTypes: props.allowedFileTypes,
    maxFiles: props.maxFiles,
    maxFileSize: props.maxFileSize,
    credits: "none",
    disabled: props.disabled,
    required: props.required,
    labelIdle: props.labelIdle,
};

defineExpose({
    resetFiles,
});
</script>

<template>
    <div class="filepond-wrapper">
        <FilePond
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

/* Custom FilePond styling */
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
