interface NoticeProps {
  type: 'error' | 'loading' | 'info';
  message: string;
}

export function Notice({ type, message }: NoticeProps) {
  return <div class={`notice notice--${type}`}>{message}</div>;
}
