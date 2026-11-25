const root = document.getElementById("calendar-root");

// Fetch Data
async function start() {
    try {
        const React = (await import("react")).default;
        const { createRoot } = await import("react-dom/client");
        const Calendar = (await import("react-calendar")).default;

        // JSX to JS translation
        const h = React.createElement;

        function CalendarComponent() {
            // Date selected
            const [date, setDate] = React.useState(new Date());

            // Mood at date selected
            const [moods, setMoods] = React.useState({ ...window.blossom.getMoods() });

            // When calendar.js saves a new mood, refresh the dots
            React.useEffect(function () {
                function refresh() {
                    setMoods({ ...window.blossom.getMoods() });
                }
                window.addEventListener("moodchange", refresh);
                return function () {
                    window.removeEventListener("moodchange", refresh);
                };
            }, []);

            // When a date is clicked, remember it and tell calendar.js
            function handleChange(newDate) {
                setDate(newDate);
                window.dispatchEvent(new CustomEvent("datechange", { detail: newDate }));
            }

            // Mood Icon
            function showMoodIcon(info) {
                const mood = moods[window.blossom.dateKey(info.date)];
                if (info.view === "month" && mood) {
                    return h("img", { className: "cal-mood-icon",
                        src: window.blossom.moodImg(mood), alt: "" 
                    });
                    return null;
                }
            }

            return h(Calendar, {
                value: date,
                onChange: handleChange,
                calendarType: "iso8601",
                locale: "en-US",
                minDetail: "month",
                prevLabel: "‹",
                nextLabel: "›",
                prev2Label: null,
                next2Label: null,
                formatShortWeekday: function (locale, d) {
                    return d.toLocaleDateString("en-US", { weekday: "short" });
                },
                tileContent: showMoodIcon
            });
        }

        createRoot(root).render(h(CalendarComponent));

    } catch (err) {
        console.error("Calendar failed to load:", err);
        root.textContent = "The calendar could not load: " + err.message;
    }
}

start();