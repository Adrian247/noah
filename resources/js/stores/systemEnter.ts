import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useSystemEnterStore = defineStore('systemEnter', () => {
    const active = ref(false);
    const message = ref('Entrando al sistema…');
    /** Fuerza remontaje de la animación en cada entrada. */
    const sessionKey = ref(0);

    function show(statusMessage = 'Entrando al sistema…') {
        message.value = statusMessage;
        if (!active.value) {
            sessionKey.value += 1;
            active.value = true;
        }
    }

    function hide() {
        active.value = false;
    }

    return { active, message, sessionKey, show, hide };
});
