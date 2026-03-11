import { ref } from "vue";

export type ConfirmDialogOptions = {
  title: string;
  message?: string;
  confirmText?: string;
  cancelText?: string;
  destructive?: boolean;
};

const isOpen = ref(false);
const options = ref<ConfirmDialogOptions>({
  title: "Confirm",
  message: "Are you sure?",
  confirmText: "Confirm",
  cancelText: "Cancel",
  destructive: false,
});

let resolver: ((accepted: boolean) => void) | null = null;

function close(value: boolean) {
  isOpen.value = false;
  if (resolver) resolver(value);
  resolver = null;
}

function confirm(next: ConfirmDialogOptions) {
  if (resolver) {
    resolver(false);
    resolver = null;
  }

  options.value = {
    title: next.title,
    message: next.message,
    confirmText: next.confirmText ?? "Confirm",
    cancelText: next.cancelText ?? "Cancel",
    destructive: next.destructive ?? false,
  };
  isOpen.value = true;

  return new Promise<boolean>((resolve) => {
    resolver = resolve;
  });
}

export function useConfirmDialog() {
  return {
    isOpen,
    options,
    confirm,
    close,
    accept: () => close(true),
    cancel: () => close(false),
  };
}
