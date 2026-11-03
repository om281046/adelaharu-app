import { apiReal, type Api } from './client';
import { apiMock } from './mock';

export const MOCK = import.meta.env.VITE_MOCK === '1';
export const api: Api = MOCK ? apiMock : apiReal;
export { ApiError, mesajEroare } from './client';
