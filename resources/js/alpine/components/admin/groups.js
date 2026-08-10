/**
 * Alpine.data('adminGroups') - Admin groups management
 *
 * The component body lives in `groups-component.js` so it can be tested
 * without booting Alpine; this file only registers it.
 */
import Alpine from '@alpinejs/csp';

import { adminGroups } from './groups-component.js';

Alpine.data('adminGroups', adminGroups);
