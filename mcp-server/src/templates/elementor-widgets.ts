/**
 * Elementor Widget Templates
 */

export const headingTemplate = {
  type: "heading",
  description: "Heading widget",
  defaultSettings: {
    title: "Add Your Heading Text Here",
    header_size: "h2",
    align: "center"
  }
};

export const textEditorTemplate = {
  type: "text-editor",
  description: "Text Editor widget",
  defaultSettings: {
    editor: "<p>I am text block. Click edit button to change this text.</p>"
  }
};

export const buttonTemplate = {
  type: "button",
  description: "Button widget",
  defaultSettings: {
    text: "Click Here",
    link: { url: "#" },
    align: "center"
  }
};

export const imageTemplate = {
  type: "image",
  description: "Image widget",
  defaultSettings: {
    image: { url: "" },
    image_size: "large"
  }
};

export const videoTemplate = {
  type: "video",
  description: "Video widget",
  defaultSettings: {
    link: "https://www.youtube.com/watch?v=XHOmBV4js_E"
  }
};

export const dividerTemplate = {
  type: "divider",
  description: "Divider widget",
  defaultSettings: {
    style: "solid",
    weight: { size: 1 }
  }
};

export const spacerTemplate = {
  type: "spacer",
  description: "Spacer widget",
  defaultSettings: {
    space: { size: 50 }
  }
};

export const containerTemplate = {
  type: "container",
  description: "Container/Section widget",
  defaultSettings: {
    content_width: "boxed"
  }
};

export const elementorTemplates = {
  heading: headingTemplate,
  "text-editor": textEditorTemplate,
  button: buttonTemplate,
  image: imageTemplate,
  video: videoTemplate,
  divider: dividerTemplate,
  spacer: spacerTemplate,
  container: containerTemplate
};
