function expandCollapse(id) {
	expandedItem = getElemRefs(id+"_e");
	collapsedItem = getElemRefs(id+"_c");
	if(expandedItem && collapsedItem) {
		if(expandedItem.style.display == "none") {
			hideDiv(id+"_c");
			showDiv(id+"_e");
		} else {
			hideDiv(id+"_e");
			showDiv(id+"_c");
		}
	}
	if(expandedItem && !collapsedItem)
	{
		if(expandedItem.style.display == "none") {
			showDiv(id+"_e");
		} else {
			hideDiv(id+"_e");
		}
	}
}

function getElemRefs(id) {
	if(document.getElementById) {
		return document.getElementById(id);
	}
	else if(document.all) {
		return document.all[id];
	}
	else if(document.layers) {
		return document.layers[id];
	}
}

function showDiv(id) {
	var lyr = getElemRefs(id);
	if(lyr && lyr.style) {
		lyr.style.display = "";
	}
}

function hideDiv(id) {
	var lyr = getElemRefs(id);
	if(lyr && lyr.style) {
		lyr.style.display = "none";
	}
}