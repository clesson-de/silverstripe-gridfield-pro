<div class="gridfield-add-new-dropdown">
    <% if $Multiple %>
        <button type="button" class="btn btn-primary dropdown-toggle gridfield-add-new-dropdown__toggle no-change-track"
            aria-haspopup="menu" aria-expanded="false" aria-controls="$MenuID.ATT" data-menu-toggle hidden><span class="font-icon-plus" aria-hidden="true"></span> $Title.XML</button>
        <div id="$MenuID.ATT" class="gridfield-pro-menu gridfield-add-new-dropdown__menu" role="menu" aria-label="$Title.ATT">
            <% loop $Choices %>
                <a class="dropdown-item action action-detail new-link gridfield-add-new-dropdown__link" role="menuitem" data-menu-item
                    href="$Link.ATT"><% if $Icon %><span class="$Icon.ATT" aria-hidden="true"></span> <% end_if %>$Label.XML</a>
            <% end_loop %>
        </div>
    <% else %>
        <% loop $Choices %>
            <a class="action action-detail new-link btn btn-primary gridfield-add-new-dropdown__direct" href="$Link.ATT"><span class="font-icon-plus" aria-hidden="true"></span> $Up.Title.XML</a>
        <% end_loop %>
    <% end_if %>
</div>
