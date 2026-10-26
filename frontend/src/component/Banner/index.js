
function Banner() {
  return (
    <aside className="ad-banner" aria-label="Publicidade">
      
      <span className="ad-label">Publicidade</span>
      <div>
        <span className="ad-eyebrow">Parceiro da rodada</span>
        <h2>Seu próximo rolê começa aqui.</h2>
        <p>Conheça ofertas de mobilidade para acompanhar seu time.</p>
      </div>
      <a className="button button-dark" href="#ofertas">Ver ofertas <span aria-hidden="true">↗</span></a>
    </aside>
  )
};

export default Banner;