document.addEventListener("DOMContentLoaded", function () {
    const memberNamesInput = document.getElementById('member-names');
    const groupMembersDiv = document.getElementById('groupmembers');
    const groupNameInput = document.getElementById('group-name');
    const groupForm = document.getElementById('groupForm');
    const groupsList = document.getElementById('groupsList');
    const noGroupsMsg = document.getElementById('noGroupsMsg');
    var names = [];

    // Preview members on input
    memberNamesInput.addEventListener('input', function () {

        names = memberNamesInput.value.split(',').map(name => name.trim()).filter(name => name);
        groupMembersDiv.innerHTML = '';
        names.forEach(name => {
            const member = document.createElement('span');
            member.textContent = name.toUpperCase();
            groupMembersDiv.appendChild(member);
        });

    });

    // Handle form submit with AJAX
    groupForm.addEventListener('submit', function (event) {
        //console.log(groupNameInput.value.trim())    
        event.preventDefault();
        if (memberNamesInput.value.trim() === '' || groupNameInput.value.trim() === '') {
            alert('Please enter both group name and member names.');
            return;
        }

        const formData = new FormData();
        console.log(names)    //checking

        formData.append('group-name', groupNameInput.value.trim());
        formData.append('member-names', names.join(','));

        fetch('create_group.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    memberNamesInput.value = '';
                    groupNameInput.value = '';
                    groupMembersDiv.innerHTML = '';
                    names = [];

                    const newGroupDiv = document.createElement('div');
                    newGroupDiv.className = 'groupItem';
                    newGroupDiv.innerHTML = `
                    <strong>${data.group.group_name}</strong><br>
                    Members: ${data.group.members}<br>
                    <div id="shareSection">
                    <p>Share with members</p>
                    <p id="grouplink"><i>http://localhost/final_splittbill/final_splittbill/join_group.php?id=${data.group.id}</i></p>
                    <button class="copylink" data-link="http://localhostfinal_splittbill/final_splittbill/join_group.php?id=${data.group.id}">Copy!</button>
                    
                    </div>
            `;
                    groupsList.insertBefore(newGroupDiv, groupsList.firstChild);
                    noGroupsMsg.style.display = 'none';

                    const copyButton = newGroupDiv.querySelector('.copylink');
                    copyButton.addEventListener('click', function () {
                        navigator.clipboard.writeText(copyButton.dataset.link).then(() => {
                            copyButton.textContent = 'Copied!';
                            setTimeout(() => { copyButton.textContent = 'Copy!'; }, 2000);
                        });
                    });

                    // const joinButton = newGroupDiv.querySelector('.joinlink');
                    // joinButton.addEventListener('click', function () {
                    //     joinGroup(joinButton.dataset.id);
                    // });

                    alert(data.message);
                } else {
                    if (data.message === 'Not logged in') {
                        alert('Session expired. Redirecting to login...');
                        window.location.href = 'login.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                }
            })
            .catch(error => {
            console.error('Fetch error:', error);
            alert('Network error: Check console for details.');
            });
    });

    // Add copy and join events to existing groups
    document.querySelectorAll('.copylink').forEach(button => {
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(this.dataset.link).then(() => {
                this.textContent = 'Copied!';
                setTimeout(() => { this.textContent = 'Copy!'; }, 2000);
            });
        });
    });
    // document.querySelectorAll('.joinlink').forEach(button => {
    //     button.addEventListener('click', function () {
    //         joinGroup(button.dataset.id);
    //     });
    // });

    // function joinGroup(groupId) {
    //     fetch(`join_group.php?id=${groupId}`, {
    //         method: 'GET'
    //     })
    //         .then(response => response.json())
    //         .then(data => {
    //             alert(data.message);
    //             if (data.success) {
    //                 location.reload(); // Reload to show the new group
    //             }
    //         })
    //         .catch(error => {
    //             alert('Network error: ' + error);
    //         });
    // }
});