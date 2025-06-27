import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";

export function useFilePond(initialFiles = [], collection = "default") {
    const files = ref([...initialFiles]);
    const tempFolders = ref([]);
    const removedFileIds = ref([]);
    const errors = ref([]);

    // Computed values
    const hasFiles = computed(
        () => files.value.length > 0 || tempFolders.value.length > 0
    );
    const totalFiles = computed(
        () => files.value.length + tempFolders.value.length
    );

    // Methods
    function handleFileAdded(data) {
        errors.value = [];
    }

    function handleFileRemoved(data) {
        if (data.type === "existing" && data.fileId) {
            const fileIndex = files.value.findIndex(
                (f) => f.id === data.fileId
            );
            if (fileIndex > -1) {
                files.value.splice(fileIndex, 1);
            }

            if (!removedFileIds.value.includes(data.fileId)) {
                removedFileIds.value.push(data.fileId);
            }
        }
    }

    function handleError(error) {
        errors.value.push(error);
    }

    function resetState() {
        tempFolders.value = [];
        removedFileIds.value = [];
        errors.value = [];
    }

    function getFormData() {
        return {
            [`${collection}_temp_folders`]: [...tempFolders.value],
            [`${collection}_removed_files`]: [...removedFileIds.value],
        };
    }

    // Submit files (move temp files to permanent storage)
    async function submitFiles(model, modelId, additionalData = {}) {
        if (
            tempFolders.value.length === 0 &&
            removedFileIds.value.length === 0
        ) {
            return Promise.resolve();
        }

        const formData = {
            model_type: model,
            model_id: modelId,
            collection: collection,
            temp_folders: tempFolders.value,
            removed_files: removedFileIds.value,
            ...additionalData,
        };

        return new Promise((resolve, reject) => {
            router.post(route("filepond.process"), formData, {
                onSuccess: () => {
                    resetState();
                    resolve();
                },
                onError: (errors) => {
                    handleError(errors);
                    reject(errors);
                },
            });
        });
    }

    return {
        // State
        files,
        tempFolders,
        removedFileIds,
        errors,

        // Computed
        hasFiles,
        totalFiles,

        // Methods
        handleFileAdded,
        handleFileRemoved,
        handleError,
        resetState,
        getFormData,
        submitFiles,
    };
}
