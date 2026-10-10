import Breadcrumbs from "./Breadcrumbs";

export default function PageHead({ crumbs, title, lead, children }) {
  return (
    <div className="page-head">
      <div className="wrap">
        <Breadcrumbs items={crumbs} />
        <h1>{title}</h1>
        {lead && <p className="page-lead">{lead}</p>}
        {children}
      </div>
    </div>
  );
}
