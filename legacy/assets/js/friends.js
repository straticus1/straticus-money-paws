document.addEventListener('DOMContentLoaded', function() {
    const pendingRequestsList = document.getElementById('pending-requests-list');

    if (pendingRequestsList) {
        pendingRequestsList.addEventListener('click', function(event) {
            if (event.target.classList.contains('respond-request-btn')) {
                const button = event.target;
                const requestId = button.dataset.requestId;
                const action = button.dataset.action;

                const formData = new FormData();
                formData.append('request_id', requestId);
                formData.append('action', action);

                fetch('/api/respond-to-friend-request.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the card from the UI
                        document.getElementById(`request-${requestId}`).remove();
                    } else {
                        alert(data.message || 'An error occurred.');
                    }
                })
                .catch(error => console.error('Error responding to request:', error));
            }
        });
    }

    const userSearchInput = document.getElementById('userSearchInput');
    const userSearchResults = document.getElementById('userSearchResults');

    if (userSearchInput) {
        userSearchInput.addEventListener('input', function() {
            const query = this.value.trim();

            if (query.length < 2) {
                userSearchResults.innerHTML = '';
                return;
            }

            fetch(`/api/search-users.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    userSearchResults.innerHTML = '';
                    if (data.success && data.users.length > 0) {
                        data.users.forEach(user => {
                            const userCard = `
                                <div class="card mb-2">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div>
                                            <img src="${user.profile_pic || 'assets/images/default_avatar.png'}" alt="${user.username}" class="rounded-circle" width="40" height="40">
                                            <strong class="ms-2">${user.username}</strong>
                                        </div>
                                        <button class="btn btn-primary btn-sm send-friend-request-btn" data-recipient-id="${user.id}">Add Friend</button>
                                    </div>
                                </div>
                            `;
                            userSearchResults.insertAdjacentHTML('beforeend', userCard);
                        });
                    } else {
                        userSearchResults.innerHTML = '<p>No users found.</p>';
                    }
                })
                .catch(error => console.error('Error searching users:', error));
        });
    }

    userSearchResults.addEventListener('click', function(event) {
        if (event.target.classList.contains('send-friend-request-btn')) {
            const recipientId = event.target.dataset.recipientId;
            const button = event.target;

            const formData = new FormData();
            formData.append('recipient_id', recipientId);

            fetch('/api/send-friend-request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = 'Request Sent';
                    button.disabled = true;
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-secondary');
                } else {
                    alert(data.message || 'An error occurred.');
                }
            })
            .catch(error => console.error('Error sending friend request:', error));
        }
    });

    const myFriendsList = document.getElementById('my-friends-list');

    if (myFriendsList) {
        myFriendsList.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-friend-btn')) {
                const button = event.target;
                const friendId = button.dataset.friendId;

                if (confirm('Are you sure you want to remove this friend?')) {
                    const formData = new FormData();
                    formData.append('friend_id', friendId);

                    fetch('/api/remove-friend.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById(`friend-${friendId}`).remove();
                        } else {
                            alert(data.message || 'An error occurred.');
                        }
                    })
                    .catch(error => console.error('Error removing friend:', error));
                }
            }
        });
    }

    const giftModal = document.getElementById('giftModal');
    if (giftModal) {
        const giftFriendName = document.getElementById('giftFriendName');
        const giftFriendIdInput = document.getElementById('giftFriendId');
        const inventoryItemSelect = document.getElementById('inventoryItemSelect');
        const sendGiftBtn = document.getElementById('sendGiftBtn');
        const giftMessage = document.getElementById('gift-message');

        giftModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const friendId = button.dataset.friendId;
            const friendName = button.dataset.friendName;

            // Reset modal state
            giftFriendName.textContent = friendName;
            giftFriendIdInput.value = friendId;
            inventoryItemSelect.innerHTML = '<option>Loading inventory...</option>';
            giftMessage.innerHTML = '';
            document.getElementById('giftQuantity').value = 1;

            // Fetch user's inventory
            fetch('/api/get-user-inventory.php')
                .then(response => response.json())
                .then(data => {
                    inventoryItemSelect.innerHTML = ''; // Clear loading message
                    if (data.success && data.inventory.length > 0) {
                        data.inventory.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = `${item.name} (Qty: ${item.quantity})`;
                            option.dataset.maxQuantity = item.quantity;
                            inventoryItemSelect.appendChild(option);
                        });
                        // Set max for quantity input based on first item
                        if (inventoryItemSelect.options.length > 0) {
                            document.getElementById('giftQuantity').max = inventoryItemSelect.options[0].dataset.maxQuantity;
                        }
                    } else {
                        inventoryItemSelect.innerHTML = '<option>Your inventory is empty.</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching inventory:', error);
                    inventoryItemSelect.innerHTML = '<option>Could not load inventory.</option>';
                });
        });

        // Update max quantity when item selection changes
        inventoryItemSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.maxQuantity) {
                document.getElementById('giftQuantity').max = selectedOption.dataset.maxQuantity;
            }
        });

        sendGiftBtn.addEventListener('click', function() {
            const giftForm = document.getElementById('giftForm');
            const formData = new FormData(giftForm);

            fetch('/api/gift-item.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                giftMessage.textContent = data.message;
                if (data.success) {
                    giftMessage.className = 'alert alert-success';
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(giftModal);
                        modal.hide();
                    }, 2000);
                } else {
                    giftMessage.className = 'alert alert-danger';
                }
            })
            .catch(error => {
                console.error('Error sending gift:', error);
                giftMessage.textContent = 'An unexpected error occurred.';
                giftMessage.className = 'alert alert-danger';
            });
        });
    }
});
