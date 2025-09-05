<div class="content p-0">
    <div class="page-header page-header-default m-10">
        @if (session("news") != '' && session("news") != null)
            <h4 class="text-danger">
                <marquee onmouseover="this.stop();" onmouseout="this.start();">{{session("news")}}</marquee>
            </h4>
        @endif
    </div>
</div>