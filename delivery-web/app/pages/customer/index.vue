
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

const { user, logout } = useAuth()

const handleLogout = async () => {
  await logout()
  await navigateTo('/')
}
</script>

<template>
  <main class="dashboard">

    <header class="topbar">
      <div class="brand">
        <div class="brand-icon">☾</div>
        <div>
          <h1>KURIR<span>MALAM</span></h1>
          <p>DELIVERY MANAGEMENT</p>
        </div>
      </div>

      <button class="logout-btn" @click="handleLogout">
        Sign out ↗
      </button>
    </header>

    <section class="welcome">
      <span class="eyebrow">CUSTOMER PORTAL</span>
      <h2>Good evening,<br /><span>{{ user?.name || 'Customer' }}.</span></h2>
      <p>Manage and track your deliveries in one place.</p>
    </section>

    <section class="stats">
      <article class="stat-card">
        <span class="stat-icon">▣</span>
        <p>TOTAL ORDERS</p>
        <h3>0</h3>
        <small>Your delivery history</small>
      </article>

      <article class="stat-card">
        <span class="stat-icon">↗</span>
        <p>IN TRANSIT</p>
        <h3>0</h3>
        <small>Currently on the way</small>
      </article>
    </section>

    <section class="actions">
      <div class="section-heading">
        <h3>Quick actions</h3>
        <span>GET STARTED</span>
      </div>

      <button class="action-card">
        <span class="action-icon">＋</span>
        <span>
          <strong>Create delivery</strong>
          <small>Send a package to its destination</small>
        </span>
        <span class="arrow">→</span>
      </button>

      <button class="action-card">
        <span class="action-icon">⌖</span>
        <span>
          <strong>Track delivery</strong>
          <small>Follow your package journey</small>
        </span>
        <span class="arrow">→</span>
      </button>
    </section>

    <section class="recent">
      <div class="section-heading">
        <h3>Recent deliveries</h3>
        <span>HISTORY</span>
      </div>

      <div class="empty-state">
        <div class="empty-icon">☾</div>
        <h4>No deliveries yet</h4>
        <p>Your delivery activity will appear here.</p>
      </div>
    </section>

    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>

  </main>
</template>

<style scoped>
.dashboard {
  min-height: 100vh;
  box-sizing: border-box;
  padding: 28px 20px;
  background:
    radial-gradient(circle at 90% 5%, #182943 0, transparent 35%),
    #080e19;
  color: #f8fafc;
  font-family: Arial, sans-serif;
}

.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 55px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-icon {
  width: 43px;
  height: 43px;
  border: 1px solid #eab44b;
  border-radius: 12px;
  display: grid;
  place-items: center;
  color: #f5bd50;
  font-size: 27px;
}

.brand h1 {
  font-size: 17px;
  letter-spacing: 1.5px;
  margin: 0;
}

.brand h1 span {
  color: #f5bd50;
}

.brand p {
  font-size: 8px;
  letter-spacing: 1.5px;
  color: #8290a5;
  margin: 5px 0 0;
}

.logout-btn {
  background: transparent;
  border: 1px solid #34445b;
  color: #cbd5e1;
  border-radius: 8px;
  padding: 9px 12px;
}

.welcome {
  margin-bottom: 32px;
}

.eyebrow,
.section-heading span {
  color: #f5bd50;
  font-size: 9px;
  font-weight: bold;
  letter-spacing: 2px;
}

.welcome h2 {
  font-size: 34px;
  line-height: 1.2;
  margin: 13px 0;
}

.welcome h2 span {
  color: #f5bd50;
}

.welcome p {
  color: #94a3b8;
  font-size: 13px;
  line-height: 1.6;
}

.stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 38px;
}

.stat-card {
  background: #111c2d;
  border: 1px solid #28364a;
  border-radius: 13px;
  padding: 17px;
}

.stat-icon,
.action-icon {
  color: #f5bd50;
  font-size: 23px;
}

.stat-card p {
  color: #94a3b8;
  font-size: 9px;
  letter-spacing: 1px;
  margin-top: 15px;
}

.stat-card h3 {
  font-size: 29px;
  margin: 8px 0;
}

.stat-card small,
.action-card small {
  color: #8290a5;
  font-size: 10px;
}

.section-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.section-heading h3 {
  font-size: 17px;
  margin: 0;
}

.actions {
  margin-bottom: 38px;
}

.action-card {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 17px;
  margin-bottom: 11px;
  border-radius: 12px;
  border: 1px solid #28364a;
  background: #111c2d;
  color: #f8fafc;
  text-align: left;
}

.action-card > span:nth-child(2) {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.action-card strong {
  font-size: 13px;
}

.arrow {
  margin-left: auto;
  color: #f5bd50;
  font-size: 20px;
}

.empty-state {
  background: #111c2d;
  border: 1px dashed #34445b;
  border-radius: 13px;
  text-align: center;
  padding: 30px 15px;
}

.empty-icon {
  color: #f5bd50;
  font-size: 30px;
}

.empty-state h4 {
  margin: 12px 0 7px;
}

.empty-state p {
  color: #8290a5;
  font-size: 11px;
}

footer {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 38px;
  color: #8290a5;
  font-size: 8px;
  letter-spacing: 1px;
}

.status-dot {
  width: 7px;
  height: 7px;
  background: #4ade80;
  border-radius: 50%;
}

.copyright {
  margin-left: auto;
  font-size: 7px;
}

@media (min-width: 700px) {
  .dashboard {
    max-width: 850px;
    margin: auto;
    padding: 45px;
  }
}
</style>
