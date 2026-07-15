{{--
    Honest label for a capability shown as an interactive preview, not faked as
    live. Mirrors the MA-DOCPORTAL <PreviewTag/> so both surfaces read the same.
    Used for backend-heavy MA features (routing engine, multi-tenancy,
    credentialing PSV, outbox dispatch) that can't be truly functional here
    without changing the host backend.
--}}
<span class="ma-pill yellow" title="Shown as a preview — the real implementation is backend-heavy in MA-DOCPORTAL">MA preview · not yet live</span>
