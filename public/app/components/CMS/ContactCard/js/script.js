// Get all elements with the class "post-card"
const contactCards = document.querySelectorAll('.contact-card');
console.log(contactCards);
// A Map to store contactCardGroups of elements. The key will be the group ID,
// and the value will be an array of elements belonging to that group.
const contactCardGroups = new Map();

// Iterate over each post card to group them by their group-id class
contactCards.forEach(card => {
    // Find the class that starts with "group-"
    const groupId = Array.from(card.classList).find(cls => cls.startsWith('group-'));

    if (groupId) {
        // If a group-id exists, add the card to the corresponding group in the map.
        if (!contactCardGroups.has(groupId)) {
            contactCardGroups.set(groupId, []);
        }
        contactCardGroups.get(groupId).push(card);
    }
});

// Now, iterate through the created contactCardGroups and wrap them
contactCardGroups.forEach(elementsInGroup => {
    if (elementsInGroup.length > 0) {
        console.log(elementsInGroup);
        // Create the new div to wrap the group
        const contactsGrid = document.createElement('div');
        contactsGrid.classList.add('contact-grid');

        // The parent node of the first element in the group
        const parentNode = elementsInGroup[0].parentNode;
        if (parentNode) {
            // Insert the new wrapper div before the first element of the group
            parentNode.insertBefore(contactsGrid, elementsInGroup[0]);

            // Move each element from the group into the new wrapper div
            // The appendChild method automatically removes the element from its old parent
            elementsInGroup.forEach(element => {
                contactsGrid.appendChild(element);
            });
        }
    }
});
