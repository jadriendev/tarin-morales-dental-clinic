export default async function handler(req, res) {
    res.status(200).json({
        reply: "Hello! AI is working."
    });
}