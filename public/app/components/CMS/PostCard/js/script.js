// Get all elements with the class "post-card"
const postCards = document.querySelectorAll('.post-card');

// A Map to store postCardGroups of elements. The key will be the group ID,
// and the value will be an array of elements belonging to that group.
const postCardGroups = new Map();

// Iterate over each post card to group them by their group-id class
postCards.forEach(card => {
    // Find the class that starts with "group-"
    const groupId = Array.from(card.classList).find(cls => cls.startsWith('group-'));

    if (groupId) {
        // If a group-id exists, add the card to the corresponding group in the map.
        if (!postCardGroups.has(groupId)) {
            postCardGroups.set(groupId, []);
        }
        postCardGroups.get(groupId).push(card);
    }
});

// Now, iterate through the created postCardGroups and wrap them
postCardGroups.forEach(elementsInGroup => {
    if (elementsInGroup.length > 0) {
        // Create the new div to wrap the group
        const postsGrid = document.createElement('div');
        postsGrid.classList.add('posts-grid');

        // The parent node of the first element in the group
        const parentNode = elementsInGroup[0].parentNode;
        if (parentNode) {
            // Insert the new wrapper div before the first element of the group
            parentNode.insertBefore(postsGrid, elementsInGroup[0]);

            // Move each element from the group into the new wrapper div
            // The appendChild method automatically removes the element from its old parent
            elementsInGroup.forEach(element => {
                postsGrid.appendChild(element);
            });
        }
    }
});
