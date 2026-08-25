import { createInertiaApp } from '@inertiajs/react';
import '@fontsource-variable/geist';

const appName = 'Statistik för Sverige';

createInertiaApp({
    title: (title: string) => (title ? `${title} – ${appName}` : appName),
    layout: () => null,
    strictMode: true,
    progress: {
        color: '#c75d50',
    },
});
