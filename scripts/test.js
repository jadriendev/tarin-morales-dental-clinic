function normalizeMessage(message) {
    return message
        .toLowerCase()
        .trim()
        .replace(/[^\w\s]/g, "");
}


function detectIntent(message) {
    const text = normalizeMessage(message);

    if (
        text.includes("hello") ||
        text.includes("hi") ||
        text.includes("good morning") ||
        text.includes("good afternoon") ||
        text.includes("good evening")
    ) {
        return "GREETING";
    }

    if (
        text.includes("clinic name") ||
        text.includes("name of clinic") ||
        text.includes("what is the clinic") ||
        text.includes("what clinic") ||
        text.includes("pangalan ng clinic") ||
        text.includes("ano pangalan ng clinic")
    ) {
        return "CLINIC_NAME";
    }

    if (
        text.includes("address") ||
        text.includes("location") ||
        text.includes("where") ||
        text.includes("saan")
    ) {
        return "ADDRESS";
    }

    if (
        text.includes("hours") ||
        text.includes("open") ||
        text.includes("schedule") ||
        text.includes("time")
    ) {
        return "HOURS";
    }

    if (
        text.includes("braces") ||
        text.includes("brace")
    ) {
        return "BRACES";
    }

    if (
        text.includes("cleaning") ||
        text.includes("linis") ||
        text.includes("teeth cleaning")
    ) {
        return "CLEANING";
    }

    if (
        text.includes("services") ||
        text.includes("offer") ||
        text.includes("treatment")
    ) {
        return "SERVICES";
    }

    if (
        text.includes("account") ||
        text.includes("new patient") ||
        text.includes("walk in") ||
        text.includes("walk-in") ||
        text.includes("register")
    ) {
        return "NEW_PATIENT";
    }

    if (
        text.includes("appointment") ||
        text.includes("book") ||
        text.includes("schedule")
    ) {
        return "APPOINTMENT";
    }

    // JB INFO
    if (
        text.includes("sino si jb") ||
        text.includes("who is jb")
    ) {
        return "JB";
    }


    // SYSTEM DEVELOPERS
    if (
        text.includes("developers") ||
        text.includes("developer") ||
        text.includes("gumawa ng system") ||
        text.includes("sino gumawa")
    ) {
        return "DEVELOPERS";
    }


    // JB FATHER IN LAW
    if (
        text.includes("father in law ni jb") ||
        text.includes("father in law") ||
        text.includes("tatay ng crush ni jb")
    ) {
        return "JB_FATHER_IN_LAW";
    }
        return "UNKNOWN";
}

function generateResponse(intent) {
    switch (intent) {
        case "GREETING":
            return responses.greeting[
                Math.floor(Math.random() * responses.greeting.length)
            ];

        case "CLINIC_NAME":
            return `The clinic name is ${knowledge.clinic.name}.`;

        case "ADDRESS":
            return `📍 ${knowledge.clinic.address}`;

        case "HOURS":
            return `${knowledge.clinic.hours.weekdays}

${knowledge.clinic.hours.saturday}

${knowledge.clinic.hours.sunday}`;

        case "BRACES":
            return `Braces start at ${knowledge.services.braces.price.total}.

Down Payment: ${knowledge.services.braces.price.downPayment}

Monthly: ${knowledge.services.braces.price.monthly}

Please visit Tarin-Morales Dental Clinic for a consultation before starting your treatment.`;

        case "CLEANING":
            return `Yes, we offer ${knowledge.services.cleaning.title}. Please visit Tarin-Morales Dental Clinic for consultation and more information.`;

        case "SERVICES":
            return `We offer the following dental services:

• Dental Cleaning
• Fillings
• Root Canals
• Tooth Extraction
• Braces
• Crowns and Bridges`;

        case "NEW_PATIENT":
            return `For new patients, we currently accept walk-in visits.

During your first visit, our staff will create your patient account and record your dental information.

Once registered, you can use your patient account to log in for future appointments.`;

        case "APPOINTMENT":
            return `You can book an appointment by visiting Tarin-Morales Dental Clinic or contacting our clinic directly for assistance.`;
        
        case "JB":
            return `Ah si JB? Siya yung pinaka pogi sa AU 😎. 
        Crush niya sina Dehins, Maricua, at Trinidad.`;

        case "DEVELOPERS":
            return `Ang developers ng system na ito ay sina:
        • Mark Fidelino
        • Jadrien Roi
        • Kurt Emmanuel
        • Ro'aisha
        • Rehndel`;

        case "JB_FATHER_IN_LAW":
            return `Ang father-in-law ni JB ay si Sir Ryan Fadrigo 😆. 
        Crush kasi ni JB ang anak niya.`;
        
        case "UNKNOWN":
        default:
            return responses.unknown[
                Math.floor(Math.random() * responses.unknown.length)
            ];
    }
}


const chatToggle = document.getElementById("chatToggle");
const chatWindow = document.getElementById("chatWindow");
const closeChat = document.getElementById("closeChat");

const sendMessage = document.getElementById("sendMessage");
const userMessage = document.getElementById("userMessage");
const chatMessages = document.getElementById("chatMessages");

chatToggle.addEventListener("click", () => {
    chatWindow.classList.toggle("hidden");
});

closeChat.addEventListener("click", () => {
    chatWindow.classList.add("hidden");
});


function addMessage(message, sender) {
    const bubble = document.createElement("div");

    if (sender === "user") {
        bubble.className =
            "bg-blue-600 text-white rounded-xl px-4 py-3 mb-3 ml-auto w-fit max-w-[80%]";
    } else {
        bubble.className =
            "bg-gray-200 text-gray-800 rounded-xl px-4 py-3 mb-3 w-fit max-w-[80%]";
    }

    bubble.textContent = message;
    chatMessages.appendChild(bubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function sendUserMessage() {
    const message = userMessage.value.trim();

    if (message === "") return;
    addMessage(message, "user");
    userMessage.value = "";

    const intent = detectIntent(message);
    const reply = generateResponse(intent);

    setTimeout(() => {
        addMessage(reply, "ai");
    }, 500);
}

sendMessage.addEventListener("click", sendUserMessage);
userMessage.addEventListener("keydown", (e) => {

    if (e.key === "Enter") {
        sendUserMessage();
    }
});

document.addEventListener("click", (e) => {
    const clickedInsideChat = chatWindow.contains(e.target);
    const clickedToggle = chatToggle.contains(e.target);

    if (!clickedInsideChat && !clickedToggle) {
        chatWindow.classList.add("hidden");
    }
});