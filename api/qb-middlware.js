import fs from "fs";
import path from "path";

export default async function handler(req, res) {
    console.log('test');
  const logFile = path.join(process.cwd(), "qbwc_debug.log");

  // --- Helper: write logs (like PHP's custom_log) ---
  function customLog(msg) {
    const line = `[${new Date().toISOString().replace("T", " ").split(".")[0]}] ${msg}\n`;
    fs.appendFileSync(logFile, line, "utf8");
  }

  // --- START: Proxy forward to another connector ---
  const remoteUrl = "https://shop.ballettechglobal.com/qb-connector.php";

  try {
    const requestBody = await req.text(); // raw XML SOAP body

    // Prepare headers
    const forwardHeaders = {
      "Content-Type": req.headers["content-type"] || "text/xml",
      "User-Agent": req.headers["user-agent"] || "Node-Proxy",
      "Accept": req.headers["accept"] || "*/*",
    };

    customLog("Forwarding to " + remoteUrl + " with headers: " + JSON.stringify(forwardHeaders));

    // Forward the request
    const response = await fetch(remoteUrl, {
      method: "POST",
      headers: forwardHeaders,
      body: requestBody,
    });

    const respBody = await response.text();
    const contentType = response.headers.get("content-type") || "text/xml";
    const httpCode = response.status;

    // Log request and response
    customLog("Request body data: " + requestBody);
    customLog("Response body data: " + respBody);
    customLog(`Proxied request to ${remoteUrl}; HTTP code: ${httpCode}; request length: ${requestBody.length}; response length: ${respBody.length}`);

    // Return response
    res.status(httpCode || 200).setHeader("Content-Type", contentType).send(respBody);
  } catch (error) {
    customLog("Proxy failed: " + error.message);
    res.status(500).send("<error>Proxy failed</error>");
  }
}
