async function sendVote() {
    const url = "https://summer.hackclub.com/votes";

    // Create form data
    const formData = new URLSearchParams({
        authenticity_token: "pT8dxVbF4VqVn7JrqYkWqIK7sj1-S3jd4vHhx7F67n0HszzjRAaoB6eTyEcZjl1Psy_uLJwResMQJr5GnNf3xA",
        "vote[ship_event_1_id]": "541",
        "vote[ship_event_2_id]": "2214",
        "vote[signature]": "eyJzaGlwX2V2ZW50XzFfaWQiOjU0MSwic2hpcF9ldmVudF8yX2lkIjoyMjE0LCJ1c2VyX2lkIjoyMTA5NiwidGltZXN0YW1wIjoxNzU0OTM3MDIwfQ%3D%3D--eb5c165e31716278769367e8851df5b912330f08ca05e0a7757e0a52ec8ceee8",
        "vote[project_1_demo_opened]": "true",
        "vote[project_1_repo_opened]": "true",
        "vote[project_2_demo_opened]": "true",
        "vote[project_2_repo_opened]": "true",
        "vote[time_spent_voting_ms]": "101512",
        "vote[music_played]": "false",
        "vote[winning_project_id]": "10839",
        "vote[explanation]": "nice dns server",
        button: ""
    });

    try {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: formData.toString(),
            credentials: "include" // include cookies if needed
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        console.log("Vote sent successfully!");
    } catch (error) {
        console.error("Error sending vote:", error);
    }
}
