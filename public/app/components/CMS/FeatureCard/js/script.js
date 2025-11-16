// Get all elements with the class "post-card"
const featureCards = document.querySelectorAll('.feature-card');

// A Map to store featureCardGroups of elements. The key will be the group ID,
// and the value will be an array of elements belonging to that group.
const featureCardGroups = new Map();

// Iterate over each post card to group them by their group-id class
featureCards.forEach(card => {
    // Find the class that starts with "group-"
    const groupId = Array.from(card.classList).find(cls => cls.startsWith('group-'));

    if (groupId) {
        // If a group-id exists, add the card to the corresponding group in the map.
        if (!featureCardGroups.has(groupId)) {
            featureCardGroups.set(groupId, []);
        }
        featureCardGroups.get(groupId).push(card);
    }
});

// Now, iterate through the created featureCardGroups and wrap them
featureCardGroups.forEach(elementsInGroup => {
    if (elementsInGroup.length > 0) {
        // Create the new div to wrap the group
        const featuresGrid = document.createElement('div');
        featuresGrid.classList.add('features-grid');

        // The parent node of the first element in the group
        const parentNode = elementsInGroup[0].parentNode;
        if (parentNode) {
            // Insert the new wrapper div before the first element of the group
            parentNode.insertBefore(featuresGrid, elementsInGroup[0]);

            // Move each element from the group into the new wrapper div
            // The appendChild method automatically removes the element from its old parent
            elementsInGroup.forEach(element => {
                featuresGrid.appendChild(element);
            });
        }
    }
});
