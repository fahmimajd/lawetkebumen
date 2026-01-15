<form class="composer" data-composer-form>
    <div class="composer__state" data-lock-state>Lock to reply</div>
    <div class="composer__reply" data-reply-preview hidden>
        <div class="composer__reply-text">
            <span class="composer__reply-label">Replying to</span>
            <span data-reply-preview-text></span>
        </div>
        <button class="composer__reply-clear" type="button" data-reply-clear>x</button>
    </div>
    <div class="composer__input">
        <textarea
            class="input input--composer"
            rows="3"
            placeholder="Write a message..."
            data-composer-input
        ></textarea>
        <input class="composer__file" type="file" data-composer-file hidden>
        <div class="composer__file-name" data-composer-file-name>No file selected</div>
        <div class="emoji-picker" data-emoji-picker hidden></div>
        <div class="quick-answers" data-quick-answers hidden></div>
    </div>
    <div class="composer__actions">
        <div class="composer__actions-left">
            <button class="button button--ghost" type="button" data-lock-toggle>Lock</button>
            <button class="button button--ghost" type="button" data-emoji-toggle>Emoji</button>
            <button class="button button--ghost" type="button" data-attach-toggle>Attach</button>
        </div>
        <button class="button button--primary" type="submit" data-send-button>Send</button>
    </div>
</form>
