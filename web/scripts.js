function toggleVisibility(id) {
    var el = document.getElementById(id);
    if (el) {
        if (el.style.display && (el.style.display == 'block' || el.style.display == ''))
            el.style.display = 'none';
        else
            el.style.display = 'block';
    }
}

function setVisibility(id, state) {
    var el = document.getElementById(id);
    el.style.display = state;
}

function getVisibility(id, state) {
    var el = document.getElementById(id);
    if (el)
        return el.style.display;
    return 'none';
}

function deleteDeviceStatus(sn, id) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {
        if (confirm('Are you sure you want to delete all status information for "' + sn + '"?')) {

            var request = new XMLHttpRequest();
            request.open("GET", "admin/admin.php?mode=ui&cmd=delstatus&sn=" + sn);
            request.addEventListener('load', function (event) {
                if (request.status >= 200 && request.status < 300) {
                    var r = JSON.parse(request.responseText);
                    if (r) {
                        el.parentNode.removeChild(el);
                    }
                }
            });
            request.send();
        }
    }
}

function touchDeviceStatus(sn, id) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {

        var request = new XMLHttpRequest();
        request.open("GET", "admin/admin.php?mode=ui&cmd=touchstatus&sn=" + sn);
        request.addEventListener('load', function (event) {
            if (request.status >= 200 && request.status < 300) {
                var r = JSON.parse(request.responseText);
                if (r) {
                    el.className = el.className.replace(/(?:^|\s)missing(?!\S)/g, '');
                }
            }
        });
        request.send();
    }
}

function deleteFile(sn, id, fn) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {
        if (confirm('Are you sure you want to delete "' + fn + '"?')) {
            var request = new XMLHttpRequest();
            request.open("GET", "admin/admin.php?mode=ui&cmd=delfile&sn=" + sn + "&file=" + encodeURIComponent(fn));
            request.addEventListener('load', function (event) {
                if (request.status >= 200 && request.status < 300) {
                    var r = JSON.parse(request.responseText);
                    if (r) {
                        updateDeviceStatus(sn, id);
                    }
                }
            });
            request.send();
        }
    }
}

function updateDeviceStatus(sn, id) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {

        var request = new XMLHttpRequest();
        request.open("GET", "admin/admin.php?mode=ui&cmd=updatestatus&sn=" + sn);
        request.addEventListener('load', function (event) {
            if (request.status >= 200 && request.status < 300) {
                var r = JSON.parse(request.responseText);
                if (r) {
                    // save and restore expandable data views
                    var msgsid = el.getAttribute("data-msgsid");
                    var backupsid = el.getAttribute("data-backupsid");
                    var scriptsid = el.getAttribute("data-scriptsid");
                    var msgsstate = getVisibility(msgsid);
                    var backupsstate = getVisibility(backupsid);
                    var scriptsstate = getVisibility(scriptsid);
                    el.outerHTML = r;
                    setVisibility(msgsid, msgsstate);
                    setVisibility(backupsid, backupsstate);
                    setVisibility(scriptsid, scriptsstate);
                }
            }
        });
        request.send();
    }
}

var updateDeviceStateTimer;
function initDeviceStateTimer(interval) {
    updateDeviceStateTimer = setInterval(updateDeviceStatusTable, interval * 1000);
    updateDeviceStatusTable();
}

function updateDeviceStatusTable() {
    var el = document.getElementById("devicestable");
    if (el) {
        var nodes = el.childNodes;
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.nodeName == "TR") {
                var sn = node.getAttribute("data-sn");
                updateDeviceStatus(sn, node.id);
            }
        }
    }
}
