import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";

export function useFilePond(initialFiles = [], collection = "default") {
  const files = ref([...initialFiles]);
  const tempFolders = ref([]);
  const removedFileIds = ref([]);
  const errors = ref([]);
  const isSubmitting = ref(false);

  // Computed values
  const hasFiles = computed(
    () => files.value.length > 0 || tempFolders.value.length > 0
  );

  const totalFiles = computed(
    () => files.value.length + tempFolders.value.length
  );

  const hasChanges = computed(
    () => tempFolders.value.length > 0 || removedFileIds.value.length > 0
  );

  const hasErrors = computed(() => errors.value.length > 0);

  // Event handlers
  function handleFileAdded(data) {
    errors.value = [];

    // Optional: Add validation or additional processing here
    if (data?.folder) {
      // The tempFolders are managed by the FileUpload component
      // This is just for additional side effects if needed
    }
  }

  function handleFileRemoved(data) {
    if (data.type === "existing" && data.fileId) {
      removeExistingFile(data.fileId);
    }
    // Temp file removals are handled by the FileUpload component
  }

  function handleError(error) {
    const errorMessage =
      typeof error === "string"
        ? error
        : error?.message || "An unknown error occurred";

    if (!errors.value.includes(errorMessage)) {
      errors.value.push(errorMessage);
    }
  }

  // Internal methods
  function removeExistingFile(fileId) {
    const fileIndex = files.value.findIndex((f) => f.id === fileId);

    if (fileIndex > -1) {
      files.value.splice(fileIndex, 1);
    }

    if (!removedFileIds.value.includes(fileId)) {
      removedFileIds.value.push(fileId);
    }
  }

  // State management
  function resetState() {
    tempFolders.value = [];
    removedFileIds.value = [];
    errors.value = [];
    isSubmitting.value = false;
  }

  function clearErrors() {
    errors.value = [];
  }

  function restoreFile(fileId) {
    const removedIndex = removedFileIds.value.indexOf(fileId);
    if (removedIndex > -1) {
      removedFileIds.value.splice(removedIndex, 1);
    }
  }

  // Data preparation
  function getFormData() {
    return {
      [`${collection}_temp_folders`]: [...tempFolders.value],
      [`${collection}_removed_files`]: [...removedFileIds.value],
    };
  }

  // Validation
  function validateSubmission() {
    const validationErrors = [];

    if (
      tempFolders.value.some((folder) => !folder || typeof folder !== "string")
    ) {
      validationErrors.push("Invalid temporary folder data");
    }

    if (
      removedFileIds.value.some(
        (id) => !id || (typeof id !== "string" && typeof id !== "number")
      )
    ) {
      validationErrors.push("Invalid removed file IDs");
    }

    return validationErrors;
  }

  // Submit files (move temp files to permanent storage)
  async function submitFiles(model, modelId, additionalData = {}) {
    // Early return if no changes
    if (!hasChanges.value) {
      return Promise.resolve({
        success: true,
        message: "No changes to submit",
      });
    }

    // Validate input parameters
    if (!model || !modelId) {
      const error = "Model and model ID are required for file submission";
      handleError(error);
      return Promise.reject(new Error(error));
    }

    // Validate submission data
    const validationErrors = validateSubmission();
    if (validationErrors.length > 0) {
      validationErrors.forEach(handleError);
      return Promise.reject(new Error("Validation failed"));
    }

    isSubmitting.value = true;
    clearErrors();

    const formData = {
      model_type: model,
      model_id: modelId,
      collection: collection,
      temp_folders: [...tempFolders.value],
      removed_files: [...removedFileIds.value],
      ...additionalData,
    };

    return new Promise((resolve, reject) => {
      router.post(route("filepond.process"), formData, {
        onSuccess: (response) => {
          resetState();
          resolve({
            success: true,
            data: response,
            message: "Files processed successfully",
          });
        },
        onError: (errors) => {
          isSubmitting.value = false;

          // Handle different error formats
          if (typeof errors === "object" && errors !== null) {
            Object.values(errors).flat().forEach(handleError);
          } else {
            handleError(errors);
          }

          reject(new Error("File submission failed"));
        },
        onFinish: () => {
          isSubmitting.value = false;
        },
      });
    });
  }

  // Batch operations
  function addMultipleFiles(newFiles) {
    if (Array.isArray(newFiles)) {
      files.value.push(...newFiles);
    }
  }

  function removeMultipleFiles(fileIds) {
    if (Array.isArray(fileIds)) {
      fileIds.forEach(removeExistingFile);
    }
  }

  // Utility methods
  function getFileById(fileId) {
    return files.value.find((f) => f.id === fileId);
  }

  function isFileRemoved(fileId) {
    return removedFileIds.value.includes(fileId);
  }

  return {
    // State
    files,
    tempFolders,
    removedFileIds,
    errors,
    isSubmitting,

    // Computed
    hasFiles,
    totalFiles,
    hasChanges,
    hasErrors,

    // Event handlers
    handleFileAdded,
    handleFileRemoved,
    handleError,

    // State management
    resetState,
    clearErrors,
    restoreFile,

    // Data operations
    getFormData,
    submitFiles,

    // Batch operations
    addMultipleFiles,
    removeMultipleFiles,

    // Utilities
    getFileById,
    isFileRemoved,
  };
}
