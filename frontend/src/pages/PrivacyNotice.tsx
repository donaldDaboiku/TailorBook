export default function PrivacyNotice() {
  return (
    <div className="privacy-notice">
      <h2 className="section-title">Privacy notice</h2>
      <p className="status">
        This notice explains what TailorMate does with personal data under the
        Nigeria Data Protection Act 2023. TailorMate is operated by gitsystemng.
      </p>
      <p className="status">
        A shop account keeps the owner’s name, email, shop name, phone,
        WhatsApp number, state, address, logo, and receipt message. The
        password is stored in a form that cannot be read back. Paystack takes
        the subscription payment. TailorMate keeps the payment reference and
        amount, and does not keep the card number.
      </p>
      <p className="status">
        The shop keeps each client’s name, phone, WhatsApp number, email,
        gender, address, notes, body measurements, jobs, and payments.
        Catalogue photos are of clothes. The shop decides what to enter and
        tells the client.
      </p>
      <p className="status">
        The shop sees its own clients. A TailorMate admin can see the shop’s
        name, email, phone, and state, and can set a new password. That reset
        is stored with the reason. The admin has no screen of client
        measurements. Paystack receives the shop email when a subscription is
        paid. An emailed receipt or notice goes through the email service. A
        WhatsApp chat stays on WhatsApp. The database and files are stored by
        Render, outside Nigeria. Render may keep a backup after a deletion for
        its own backup period.
      </p>
      <p className="status">
        A client stays until the shop erases that client or deletes the shop
        account. Archive only hides a client. Deleting the account removes the
        login, shop, clients, measurements, jobs, payments, expenses, logo,
        catalogue photos, and feedback sent from that login. A referral note
        that named the shop is rewritten so the name is gone. Paystack keeps
        its own payment record.
      </p>
      <p className="status">
        On More, the shop can download every record or delete the account. On
        a client, the shop can correct the details, download that client’s
        file, or erase the client. A client asks the shop for a copy, a
        correction, or deletion.
      </p>
      <p className="status">You must be 18 or older to open a shop.</p>
      <p className="status">
        If we learn of a breach that is likely to risk people’s rights, we
        tell the Nigeria Data Protection Commission within 72 hours, and we
        tell the affected shops when the risk is high.
      </p>
      <p className="status">
        Write to gitsystemng through{' '}
        <a href="https://www.gitsystemng.com" target="_blank" rel="noreferrer">
          gitsystemng.com
        </a>
        , or to the Nigeria Data Protection Commission at{' '}
        <a href="https://ndpc.gov.ng" target="_blank" rel="noreferrer">
          ndpc.gov.ng
        </a>
        .
      </p>
    </div>
  )
}
