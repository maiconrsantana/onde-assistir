function Nav({ period, onPrevious, onNext }) {
  return (
    <nav className="period-nav" aria-label="Período das partidas">
      <button className={period === 'previous' ? 'active' : ''} type="button" onClick={onPrevious}>← Jogos anteriores</button>
      <button className={period === 'next' ? 'active' : ''} type="button" onClick={onNext}>Próximos jogos →</button>
    </nav>
  );
}

export default Nav;

