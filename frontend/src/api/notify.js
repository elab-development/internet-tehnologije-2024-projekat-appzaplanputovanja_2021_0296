import Swal from "sweetalert2";

// generic extract (Laravel-style JSON errors)
export function extractMessage(err) {
  // Validation errors
  if (err?.response?.status === 422 && err.response.data?.errors) {
    const first = Object.values(err.response.data.errors)[0];
    return Array.isArray(first) ? first[0] : String(first);
  }
  // Other messages
  return (
    err?.response?.data?.message ||
    err?.message ||
    "Something went wrong. Please try again."
  );
}

// Success message
export function showSuccess(message = "Saved successfully!") {
  return Swal.fire({
    icon: "success",
    title: "Success",
    text: message,
    confirmButtonText: "OK",
  });
}

// Error message
export function showError(message = "An error occurred", details = null) {
  let html = "";

  // 1) Ako stigne string (npr. već složen <ul>…), samo ga prikaži
  if (typeof details === "string" && details.trim()) {
    html = details;
  }
  // 2) Ako stigne objekat (npr. Laravel errors{}), pretvori u <ul>
  else if (details && typeof details === "object") {
    const lines = [];
    Object.values(details).forEach((arr) => {
      (Array.isArray(arr) ? arr : [arr]).forEach((msg) => lines.push(msg));
    });
    if (lines.length) {
      html =
        `<ul style="text-align:left">` +
        lines.map((li) => `<li>${li}</li>`).join("") +
        `</ul>`;
    }
  }

  return Swal.fire({
    icon: "error",
    title: "Error",
    text: html ? "" : message,
    html: html || undefined,
    confirmButtonText: "Close",
  });
}

// Info message
export function showInfo(message, title = "Information") {
  return Swal.fire({
    icon: "info",
    title,
    text: message,
    confirmButtonText: "OK",
  });
}

// Confirmation dialog (za delete i slične radnje)
export async function confirmDialog({
  title = "Are you sure?",
  text = "This action cannot be undone.",
  confirmText = "Yes",
  cancelText = "Cancel",
  icon = "warning",
} = {}) {
  const res = await Swal.fire({
    icon,
    title,
    text,
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
    reverseButtons: true,
  });
  return res.isConfirmed;
}

// Toast notification (za kratke poruke)
export function toast(message, icon = "info") {
  return Swal.fire({
    toast: true,
    position: "top-end",
    icon,
    title: message,
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
  });
}
