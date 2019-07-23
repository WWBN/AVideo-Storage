<style>
    iframe {
        display: block;       /* iframes are inline by default */
        background: #000;
        border: none;         /* Reset default border */
        height: calc(100vh - 43px);        /* Viewport-relative units */
        width: 100vw;
    }
</style>

<ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="contact-tab" data-toggle="tab" href="#tinyfilemanager" role="tab" aria-controls="tinyfilemanager" aria-selected="true">File Manager</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="profile-tab" data-toggle="tab" href="#phpsysinfo" role="tab" aria-controls="phpsysinfo" aria-selected="false">SYS Info</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="Logout.php">Logout</a>
    </li>
</ul>
<div class="tab-content" id="myTabContent">
    <div class="tab-pane fade show active" id="tinyfilemanager" role="tabpanel" aria-labelledby="tinyfilemanager-tab">
        <iframe src="tinyfilemanager.php"></iframe>
    </div>
    <div class="tab-pane fade" id="phpsysinfo" role="tabpanel" aria-labelledby="phpsysinfo-tab">
        <iframe src="phpsysinfo/index.php"></iframe>
    </div>
</div>