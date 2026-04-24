const donorToastBus = new EventTarget();

export function showDonorToast(message, type = 'success') {
  donorToastBus.dispatchEvent(new CustomEvent('donor-toast', {
    detail: {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      message,
      type,
    },
  }));
}

export function subscribeDonorToast(handler) {
  const listener = (event) => handler(event.detail);
  donorToastBus.addEventListener('donor-toast', listener);

  return () => {
    donorToastBus.removeEventListener('donor-toast', listener);
  };
}