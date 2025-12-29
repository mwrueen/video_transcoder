# Frontend Integration Guide

## Video Transcoding Status

### Status Values

The video transcoding goes through the following statuses:

1. **`pending`** - Video uploaded, waiting in queue for transcoding
2. **`processing`** - Currently being transcoded by FFmpeg
3. **`completed`** - Transcoding finished successfully, HLS files are ready
4. **`failed`** - Transcoding failed (check `error_message` field)

### Transcoding Duration

Transcoding time depends on:
- Video file size and duration
- Server CPU resources
- Number of quality variants (6 variants: 144p, 240p, 360p, 480p, 720p, 1080p)

**Typical times:**
- Small videos (< 100MB): 2-5 minutes
- Medium videos (100-500MB): 5-15 minutes
- Large videos (> 500MB): 15-60+ minutes

## Checking Transcoding Status

### API Endpoint

```
GET /api/v1/videos/{uuid}
```

### Response Structure

```json
{
    "success": true,
    "data": {
        "id": "4717f050-71ed-4081-a1c3-d38cac75a50e",
        "status": "pending|processing|completed|failed",
        "hls_path": "hls/{uuid}/master.m3u8",  // null until completed
        "hls_url": "http://localhost:8000/api/v1/videos/{uuid}/hls/master.m3u8",
        "hls_file_path": "/full/path/to/master.m3u8",  // null until completed
        "transcoding_started_at": "2025-12-28T20:31:46+00:00",
        "transcoding_completed_at": "2025-12-28T20:35:12+00:00",
        "error_message": null  // Only present if status is "failed"
    }
}
```

## Frontend Implementation Examples

### JavaScript/TypeScript - Polling Example

```javascript
/**
 * Poll video status until transcoding completes
 * @param {string} videoUuid - The video UUID
 * @param {number} intervalMs - Polling interval in milliseconds (default: 3000)
 * @param {number} maxAttempts - Maximum polling attempts (default: 200 = 10 minutes)
 * @returns {Promise<Object>} Video data when completed
 */
async function waitForTranscoding(videoUuid, intervalMs = 3000, maxAttempts = 200) {
    const apiUrl = `http://localhost:8000/api/v1/videos/${videoUuid}`;
    
    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        try {
            const response = await fetch(apiUrl);
            const result = await response.json();
            
            if (result.success && result.data) {
                const video = result.data;
                
                // Check if transcoding is completed
                if (video.status === 'completed') {
                    console.log('Transcoding completed!', video);
                    return video;
                }
                
                // Check if transcoding failed
                if (video.status === 'failed') {
                    throw new Error(`Transcoding failed: ${video.error_message || 'Unknown error'}`);
                }
                
                // Log progress
                console.log(`Status: ${video.status} (attempt ${attempt + 1}/${maxAttempts})`);
                
                // Wait before next poll
                await new Promise(resolve => setTimeout(resolve, intervalMs));
            } else {
                throw new Error('Failed to fetch video status');
            }
        } catch (error) {
            console.error('Error checking status:', error);
            throw error;
        }
    }
    
    throw new Error('Transcoding timeout - exceeded maximum attempts');
}

// Usage example
const videoUuid = '4717f050-71ed-4081-a1c3-d38cac75a50e';

waitForTranscoding(videoUuid)
    .then(video => {
        console.log('Video ready!', video.hls_url);
        // Use video.hls_url to play the video
    })
    .catch(error => {
        console.error('Error:', error);
    });
```

### React Hook Example

```jsx
import { useState, useEffect } from 'react';

function useVideoStatus(videoUuid, pollInterval = 3000) {
    const [video, setVideo] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!videoUuid) return;

        let intervalId;
        let attempts = 0;
        const maxAttempts = 200; // 10 minutes max

        const checkStatus = async () => {
            try {
                const response = await fetch(
                    `http://localhost:8000/api/v1/videos/${videoUuid}`
                );
                const result = await response.json();

                if (result.success && result.data) {
                    const videoData = result.data;
                    setVideo(videoData);
                    setLoading(false);

                    // Stop polling if completed or failed
                    if (videoData.status === 'completed' || videoData.status === 'failed') {
                        clearInterval(intervalId);
                        if (videoData.status === 'failed') {
                            setError(videoData.error_message || 'Transcoding failed');
                        }
                    } else if (attempts >= maxAttempts) {
                        clearInterval(intervalId);
                        setError('Transcoding timeout');
                    }
                } else {
                    setError('Failed to fetch video status');
                    clearInterval(intervalId);
                }
            } catch (err) {
                setError(err.message);
                clearInterval(intervalId);
            }
            
            attempts++;
        };

        // Initial check
        checkStatus();

        // Set up polling
        intervalId = setInterval(checkStatus, pollInterval);

        // Cleanup
        return () => clearInterval(intervalId);
    }, [videoUuid, pollInterval]);

    return { video, loading, error };
}

// Usage in component
function VideoPlayer({ videoUuid }) {
    const { video, loading, error } = useVideoStatus(videoUuid);

    if (loading) {
        return <div>Transcoding in progress... Status: {video?.status}</div>;
    }

    if (error) {
        return <div>Error: {error}</div>;
    }

    if (video?.status === 'completed') {
        return (
            <video controls>
                <source src={video.hls_url} type="application/vnd.apple.mpegurl" />
                Your browser does not support HLS video.
            </video>
        );
    }

    return <div>Status: {video?.status}</div>;
}
```

### Vue.js Example

```vue
<template>
    <div>
        <div v-if="loading">Transcoding in progress... Status: {{ video?.status }}</div>
        <div v-else-if="error">Error: {{ error }}</div>
        <video v-else-if="video?.status === 'completed'" controls>
            <source :src="video.hls_url" type="application/vnd.apple.mpegurl" />
            Your browser does not support HLS video.
        </video>
        <div v-else>Status: {{ video?.status }}</div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            video: null,
            loading: true,
            error: null,
            pollInterval: null
        };
    },
    props: {
        videoUuid: {
            type: String,
            required: true
        }
    },
    mounted() {
        this.checkStatus();
        this.pollInterval = setInterval(this.checkStatus, 3000);
    },
    beforeUnmount() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    },
    methods: {
        async checkStatus() {
            try {
                const response = await fetch(
                    `http://localhost:8000/api/v1/videos/${this.videoUuid}`
                );
                const result = await response.json();

                if (result.success && result.data) {
                    this.video = result.data;
                    this.loading = false;

                    if (this.video.status === 'completed' || this.video.status === 'failed') {
                        clearInterval(this.pollInterval);
                        if (this.video.status === 'failed') {
                            this.error = this.video.error_message || 'Transcoding failed';
                        }
                    }
                }
            } catch (err) {
                this.error = err.message;
                clearInterval(this.pollInterval);
            }
        }
    }
};
</script>
```

## Using HLS.js for Video Playback

For browsers that don't natively support HLS (like Chrome, Firefox), use HLS.js:

```html
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<video id="video" controls></video>

<script>
const video = document.getElementById('video');
const videoSrc = 'http://localhost:8000/api/v1/videos/4717f050-71ed-4081-a1c3-d38cac75a50e/hls/master.m3u8';

if (Hls.isSupported()) {
    const hls = new Hls();
    hls.loadSource(videoSrc);
    hls.attachMedia(video);
} else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    // Native HLS support (Safari)
    video.src = videoSrc;
}
</script>
```

## Best Practices

1. **Polling Interval**: Use 3-5 seconds for polling to avoid excessive API calls
2. **Timeout**: Set a reasonable timeout (e.g., 10-15 minutes) to avoid infinite polling
3. **Error Handling**: Always handle failed status and show error messages to users
4. **Progress Indication**: Show the current status (pending/processing) to users
5. **Exponential Backoff**: Consider increasing polling interval over time to reduce server load

## Alternative: WebSocket/SSE (Future Enhancement)

For real-time updates without polling, you could implement:
- WebSocket connection for live status updates
- Server-Sent Events (SSE) for push notifications
- Webhook callbacks when transcoding completes

