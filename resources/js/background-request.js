/*
 * Requests the page makes on its own (polls, live-update reloads, socket authorization) send this
 * header, so the server never counts them as user activity for idle sign-out
 * (app/Support/IdleTimeout.php). Anything a person clicks or types must not use it.
 */
export const BACKGROUND_HEADERS = Object.freeze({ 'X-LSPDMS-Background': '1' });
