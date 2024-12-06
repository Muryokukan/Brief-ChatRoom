document.addEventListener('DOMContentLoaded', () => {
    const sendButton = document.getElementById('sendButton');

    if (sendButton) {
        sendButton.addEventListener('click', () => {
            const text = document.getElementById('textInput').value;
            const endpoint = "{{ path('room_publish', {'roomId': roomId}) }}";

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message: text }),
            })
                .then(response => {
                    if (response.ok) {
                        return response.json();
                    }
                    throw new Error('Failed to send message');
                })
                .then(data => {
                    alert('Message sent successfully: ' + JSON.stringify(data));
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        });
    }
});
