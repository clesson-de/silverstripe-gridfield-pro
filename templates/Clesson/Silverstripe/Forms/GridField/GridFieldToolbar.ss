<div class="gridfield-pro-toolbar" role="group" aria-label="$Label.ATT">
    <% loop $Rows %>
        <div class="gridfield-pro-toolbar__row">
            <% if $Full %>
                <% loop $Full %><div class="gridfield-pro-toolbar__item gridfield-pro-toolbar__item--full">$Fragment</div><% end_loop %>
            <% else %>
                <div class="gridfield-pro-toolbar__group gridfield-pro-toolbar__group--left">
                    <% if $FilterLabel %><span class="gridfield-pro-toolbar__label"><span class="font-icon-search" aria-hidden="true"></span> $FilterLabel.XML</span><% end_if %>
                    $LegacyLeft
                    <% loop $Left %><div class="gridfield-pro-toolbar__item gridfield-pro-toolbar__item--$Width.ATT">$Fragment</div><% end_loop %>
                </div>
                <div class="gridfield-pro-toolbar__group gridfield-pro-toolbar__group--right">
                    <% if $ActionLabel %><span class="gridfield-pro-toolbar__label">$ActionLabel.XML</span><% end_if %>
                    $LegacyRight
                    <% loop $Right %><div class="gridfield-pro-toolbar__item gridfield-pro-toolbar__item--$Width.ATT">$Fragment</div><% end_loop %>
                </div>
            <% end_if %>
        </div>
    <% end_loop %>
</div>
