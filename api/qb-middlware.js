export const config = {
  api: {
    bodyParser: false, // needed to get raw XML body
  },
};

export default async function handler(req, res) {
  // --- Helper: log to console (use Vercel logs instead of file) ---
  function customLog(msg) {
    console.log(`[${new Date().toISOString()}] ${msg}`);
  }

  // --- START: Proxy forward to another connector ---
  const remoteUrl = "https://shop.ballettechglobal.com/qb-connector.php";
  //const remoteUrl = "https://tickets.gokuldham.org/qb-connector.php";

  try {
    // Read raw body
    const chunks = [];
    for await (const chunk of req) {
      chunks.push(chunk);
    }
    const requestBody = Buffer.concat(chunks).toString();

    // Prepare headers
    const forwardHeaders = {
      "Content-Type": req.headers["content-type"] || "text/xml",
      "User-Agent": req.headers["user-agent"] || "Node-Proxy",
      "Accept": req.headers["accept"] || "*/*",
    };

    customLog("Forwarding request to " + remoteUrl);

    // Forward request
    const response = await fetch(remoteUrl, {
      method: "POST",
      headers: forwardHeaders,
      body: requestBody,
    });

    const respBody = await response.text();
    const contentType = response.headers.get("content-type") || "text/xml";
    const httpCode = response.status;

    customLog("Request length: " + requestBody.length + " bytes");
    customLog("Response length: " + respBody.length + " bytes");

    // Return response to QBWC
    res.status(httpCode || 200).setHeader("Content-Type", contentType).send(respBody);
  } catch (error) {
    customLog("Proxy failed: " + error.message);
    res.status(500).send("<error>Proxy failed: " + error.message + "</error>");
  }
}
