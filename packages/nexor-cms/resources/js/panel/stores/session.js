import { defineStore } from 'pinia';
import { api } from '../api';

/**
 * Who is signed in, what they may do, and the infoblocks they can open.
 * Loaded once from /bootstrap before the router renders anything.
 */
export const useSession = defineStore('session', {
    state: () => ({
        user: null,
        permissions: [],
        isSuperAdmin: false,
        brand: { name: 'NEXOR', initial: 'N', site_name: 'NEXOR', version: '' },
        iblocks: [],
        propertyTypes: [],
        paginationTemplates: [],
        routes: {},
        ready: false,
        error: null,
    }),

    getters: {
        /** Super admins pass every check, mirroring the server-side gate. */
        can: (state) => (code) => state.isSuperAdmin || state.permissions.includes(code),

        canAny: (state) => (codes) => state.isSuperAdmin || codes.some((code) => state.permissions.includes(code)),

        iblock: (state) => (id) => state.iblocks.find((item) => String(item.id) === String(id)) ?? null,

        propertyType: (state) => (value) => state.propertyTypes.find((type) => type.value === value) ?? null,

        propertyTypeGroups: (state) => {
            const groups = {};

            state.propertyTypes.forEach((type) => {
                (groups[type.group] ??= []).push(type);
            });

            return groups;
        },
    },

    actions: {
        async load() {
            try {
                const data = await api.get('bootstrap');

                this.user = data.user.data ?? data.user;
                this.permissions = data.permissions ?? [];
                this.isSuperAdmin = data.is_super_admin;
                this.brand = data.brand;
                this.iblocks = data.iblocks.data ?? data.iblocks;
                this.propertyTypes = data.property_types;
                this.paginationTemplates = data.pagination_templates ?? [];
                this.routes = data.routes;
                this.ready = true;
            } catch (error) {
                this.error = error;

                // The session expired while the SPA was open — go back to the login screen.
                if (error.status === 401 || error.status === 419) {
                    window.location.reload();
                }
            }
        },

        /** Refresh the infoblock list after one is created, renamed or removed. */
        async refreshIblocks() {
            const data = await api.get('bootstrap');

            this.iblocks = data.iblocks.data ?? data.iblocks;
            this.permissions = data.permissions ?? [];
        },
    },
});
