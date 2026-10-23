/**
 * Dynamic Slot Fetching & Selection Logic (Modern Pill Design)
 */
document.addEventListener('DOMContentLoaded', () => {
    const serviceSelect = document.getElementById('service_id');
    const dentistSelect = document.getElementById('dentist_id');
    const dateInput = document.getElementById('appointment_date');
    const slotsContainer = document.getElementById('slotsContainer');
    const hiddenStartTime = document.getElementById('selected_start_time');
    const submitBtn = document.getElementById('submitBtn');

    const defaultBtnClass = 'slot-btn py-2.5 px-3 text-xs font-bold rounded-xl border border-slate-200 bg-white text-slate-700 hover:border-teal-500 hover:text-teal-700 shadow-sm transition';
    const activeBtnClass  = 'slot-btn py-2.5 px-3 text-xs font-extrabold rounded-xl border-2 border-teal-600 bg-teal-600 text-white shadow-md shadow-teal-600/20 transition';

    async function fetchAvailableSlots() {
        const serviceId = serviceSelect.value;
        const dentistId = dentistSelect.value;
        const date = dateInput.value;

        hiddenStartTime.value = '';
        submitBtn.disabled = true;

        if (!serviceId || !dentistId || !date) {
            slotsContainer.textContent = 'Select a procedure, dentist, and date above to load available time slots.';
            return;
        }

        slotsContainer.textContent = 'Checking dentist schedule & real-time slot conflicts...';

        try {
            const params = new URLSearchParams({
                service_id: serviceId,
                dentist_id: dentistId,
                date: date
            });

            const apiUrl = slotsContainer.dataset.apiUrl || '../api/slots.php';
            const response = await fetch(`${apiUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            slotsContainer.innerHTML = '';

            if (!data.success || !Array.isArray(data.slots) || data.slots.length === 0) {
                slotsContainer.textContent = data.message || 'No available time slots for this dentist on the selected date.';
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-2 sm:grid-cols-3 gap-2.5';

            data.slots.forEach((slot) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = slot.label;
                btn.className = defaultBtnClass;

                btn.addEventListener('click', () => {
                    document.querySelectorAll('.slot-btn').forEach((b) => {
                        b.className = defaultBtnClass;
                    });
                    btn.className = activeBtnClass;
                    hiddenStartTime.value = slot.start_time;
                    submitBtn.disabled = false;
                });

                grid.appendChild(btn);
            });

            slotsContainer.appendChild(grid);
        } catch (err) {
            slotsContainer.textContent = 'Unable to load time slots. Please try again.';
        }
    }

    serviceSelect.addEventListener('change', fetchAvailableSlots);
    dentistSelect.addEventListener('change', fetchAvailableSlots);
    dateInput.addEventListener('change', fetchAvailableSlots);
});
