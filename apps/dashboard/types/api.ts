export interface ApiMeta {
  request_id: string;
  timestamp: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  meta: ApiMeta;
}

export interface ApiErrorResponse {
  success: false;
  error: {
    code: string;
    message: string;
    details?: Array<{ field: string; issue: string }>;
  };
  meta: ApiMeta;
}

export interface AuthUser {
  user_id: number;
  tenant_id: number;
  name: string;
  email: string;
  phone: string | null;
  roles: string[];
  permissions: string[];
  locations: number[];
}

export interface LoginResponse {
  token: string;
  user: AuthUser;
  expires_at: string | null;
}

export interface LocationSummary {
  location_id: number;
  name: string;
  short_code: string;
  mode: string;
  status: string;
}

export interface EdgeDeviceState {
  device_uid: string;
  status: string;
  last_heartbeat_at: string | null;
}

export interface LocationState {
  location_id: number;
  location_name: string;
  short_code: string;
  as_of: string;
  quota: number;
  issued: number;
  tokens_remaining: number;
  queue_head: number;
  queue_tail: number;
  cutoff_position: number | null;
  status: string;
  issuance_rate_per_min: number;
  arrival_rate_per_min: number;
  last_event_at: string | null;
  edge_devices: EdgeDeviceState[];
}

export interface QueueEventItem {
  queue_event_id: number;
  event_type: string;
  occurred_at: string;
  received_at: string;
  edge_device_id: number;
  camera_id: number | null;
  track_id: string | null;
  confidence: number | null;
}

export interface PaginatedEvents {
  items: QueueEventItem[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface LocationStateUpdatedEvent {
  location_id: number;
  as_of: string;
  tokens_remaining: number;
  queue_head: number;
  queue_tail: number;
  cutoff_position: number | null;
  status: string;
  delta: { cause: string };
}
